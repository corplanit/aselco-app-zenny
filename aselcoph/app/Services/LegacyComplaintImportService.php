<?php

namespace App\Services;

use App\Models\CustomerComplaint;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LegacyComplaintImportService
{
    public const CREATED_BY_PREFIX = 'legacy-complaint:';

    /**
     * @return array{imported: int, skipped: int, failed: int, tickets: list<array{complaint_id: int, ticket_id: int, ticket_no: string, status: string}>}
     */
    public function import(bool $dryRun = false, bool $fresh = false): array
    {
        $stats = [
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'tickets' => [],
        ];

        if (! Schema::hasTable('customer_complaints') || ! Schema::hasTable('tickets')) {
            return $stats;
        }

        if ($fresh && ! $dryRun) {
            $this->removeImported();
        }

        $categories = TicketCategory::query()->get()->keyBy('department_code');
        if ($categories->isEmpty()) {
            return $stats;
        }

        CustomerComplaint::query()
            ->orderBy('id')
            ->each(function (CustomerComplaint $complaint) use ($dryRun, $categories, &$stats): void {
                $createdBy = self::createdByKey((int) $complaint->id);
                $existing = Ticket::query()->where('created_by', $createdBy)->first();
                if ($existing) {
                    $stats['skipped']++;

                    return;
                }

                if ($dryRun) {
                    $stats['imported']++;
                    $stats['tickets'][] = [
                        'complaint_id' => (int) $complaint->id,
                        'ticket_id' => 0,
                        'ticket_no' => $this->makeTicketNo($complaint),
                        'status' => $this->mapStatus($complaint),
                    ];

                    return;
                }

                try {
                    $ticket = DB::transaction(function () use ($complaint, $createdBy, $categories): Ticket {
                        return $this->importOne($complaint, $createdBy, $categories);
                    });

                    $stats['imported']++;
                    $stats['tickets'][] = [
                        'complaint_id' => (int) $complaint->id,
                        'ticket_id' => (int) $ticket->id,
                        'ticket_no' => $ticket->ticket_no,
                        'status' => $ticket->status,
                    ];
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    report($e);
                }
            });

        return $stats;
    }

    public static function createdByKey(int $complaintId): string
    {
        return self::CREATED_BY_PREFIX.$complaintId;
    }

    public function removeImported(): int
    {
        return Ticket::query()
            ->where('created_by', 'like', self::CREATED_BY_PREFIX.'%')
            ->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, TicketCategory>  $categories
     */
    private function importOne(CustomerComplaint $complaint, string $createdBy, $categories): Ticket
    {
        $customer = $this->resolveCustomer($complaint);
        $category = $this->resolveCategory($complaint, $categories);
        $status = $this->mapStatus($complaint);
        $createdAt = $complaint->created_at ?? now();
        $updatedAt = $complaint->updated_at ?? $createdAt;

        $ticket = new Ticket([
            'ticket_no' => $this->makeTicketNo($complaint),
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'subcategory' => $this->subcategory($complaint),
            'channel' => 'app',
            'description' => $this->description($complaint),
            'status' => $status,
            'priority' => $this->mapPriority($complaint),
            'assigned_to' => null,
            'assigned_department' => null,
            'sla_due_at' => null,
            'created_by' => $createdBy,
        ]);
        $ticket->created_at = $createdAt;
        $ticket->updated_at = $updatedAt;
        $ticket->save();

        $this->writeHistory($ticket, $status, $createdAt, (int) $complaint->id);
        $this->copyAttachment($complaint, $ticket, $customer);

        return $ticket;
    }

    private function resolveCustomer(CustomerComplaint $complaint): User
    {
        $userId = Schema::hasColumn('customer_complaints', 'user_id')
            ? $complaint->user_id
            : null;

        if ($userId) {
            $user = User::query()->find($userId);
            if ($user) {
                return $user;
            }
        }

        $email = 'legacy-complaint-'.$complaint->id.'@imported.aselco.local';
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        return User::query()->create([
            'name' => trim((string) ($complaint->name ?: 'Legacy complainant')),
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => $complaint->created_at ?? now(),
            'role' => 'customer',
            'account_status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, TicketCategory>  $categories
     */
    private function resolveCategory(CustomerComplaint $complaint, $categories): TicketCategory
    {
        $text = mb_strtolower(trim(implode(' ', array_filter([
            $complaint->complaint,
            Schema::hasColumn('customer_complaints', 'subject') ? $complaint->subject : null,
            Schema::hasColumn('customer_complaints', 'category') ? $complaint->category : null,
            Schema::hasColumn('customer_complaints', 'concern_type') ? $complaint->concern_type : null,
        ]))));

        $department = 'FOCAL';

        if ($this->contains($text, [
            'brownout', 'outage', 'no power', 'power interruption', 'blackout',
            'low voltage', 'voltage', 'downed line', 'sparking',
        ])) {
            $department = 'COMD';
        } elseif ($this->contains($text, ['dashboard', 'feature request', 'ma add ang'])) {
            $department = 'ISD-CCSMDD';
        } elseif ($this->contains($text, [
            'bill', 'billing', 'gcash', 'maya', 'pay', 'payment', 'soa',
            'ledger', 'overcharge', 'kurente', 'due date', 'statement of account',
        ])) {
            $department = 'CCAD';
        } elseif ($this->contains($text, ['meter', 'connection', 'kwh'])) {
            $department = 'AO-CDS';
        }

        return $categories->get($department) ?? $categories->get('FOCAL') ?? $categories->first();
    }

    /**
     * @param  list<string>  $needles
     */
    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function mapStatus(CustomerComplaint $complaint): string
    {
        if (! Schema::hasColumn('customer_complaints', 'status')) {
            return Ticket::STATUS_NEW;
        }

        return match (mb_strtolower(trim((string) $complaint->status))) {
            'in progress', 'in_progress' => Ticket::STATUS_IN_PROGRESS,
            'resolved' => Ticket::STATUS_RESOLVED,
            'rejected', 'closed' => Ticket::STATUS_CLOSED,
            default => Ticket::STATUS_NEW,
        };
    }

    private function mapPriority(CustomerComplaint $complaint): string
    {
        if (! Schema::hasColumn('customer_complaints', 'priority')) {
            return 'normal';
        }

        return match (mb_strtolower(trim((string) $complaint->priority))) {
            'urgent' => 'urgent',
            'high' => 'high',
            'low' => 'low',
            default => 'normal',
        };
    }

    private function subcategory(CustomerComplaint $complaint): string
    {
        if (Schema::hasColumn('customer_complaints', 'reference_number') && filled($complaint->reference_number)) {
            return (string) $complaint->reference_number;
        }

        return 'Legacy #'.$complaint->id;
    }

    private function description(CustomerComplaint $complaint): string
    {
        return trim((string) $complaint->complaint);
    }

    private function makeTicketNo(CustomerComplaint $complaint): string
    {
        $stamp = Carbon::parse($complaint->created_at ?? now())->format('YmdHis');

        return 'TKT-'.$stamp.'-L'.str_pad((string) $complaint->id, 4, '0', STR_PAD_LEFT);
    }

    private function writeHistory(Ticket $ticket, string $status, Carbon $at, int $complaintId): void
    {
        $created = new TicketStatusHistory([
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => Ticket::STATUS_NEW,
            'changed_by' => null,
            'remarks' => 'Imported from legacy complaint #'.$complaintId.'.',
        ]);
        $created->created_at = $at;
        $created->save();

        if ($status === Ticket::STATUS_NEW) {
            return;
        }

        $follow = new TicketStatusHistory([
            'ticket_id' => $ticket->id,
            'from_status' => Ticket::STATUS_NEW,
            'to_status' => $status,
            'changed_by' => null,
            'remarks' => 'Status carried over from the legacy complaint.',
        ]);
        $follow->created_at = $ticket->updated_at ?? $at;
        $follow->save();
    }

    private function copyAttachment(CustomerComplaint $complaint, Ticket $ticket, User $customer): void
    {
        if (! Schema::hasColumn('customer_complaints', 'attachment') || blank($complaint->attachment)) {
            return;
        }

        $source = (string) $complaint->attachment;
        if (! Storage::disk('public')->exists($source)) {
            return;
        }

        $disk = (string) config('tickets.attachment.disk', 'local');
        $directory = trim((string) config('tickets.attachment.directory', 'tickets')).'/'.$ticket->id;
        $destination = $directory.'/'.basename($source);

        Storage::disk($disk)->put($destination, Storage::disk('public')->get($source));

        TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $customer->id,
            'file_path' => $destination,
            'file_type' => $this->guessMime($source),
            'file_size' => (int) Storage::disk($disk)->size($destination),
        ]);
    }

    private function guessMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
