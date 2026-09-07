<?php

namespace Tests\Feature;

use App\Models\CustomerComplaint;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\LegacyComplaintImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyComplaintImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_pending_complaints_as_open_queue_tickets(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config()->set('tickets.attachment.disk', 'local');
        config()->set('tickets.attachment.directory', 'tickets');

        $customer = User::factory()->create([
            'name' => 'Legacy Customer',
            'role' => 'customer',
        ]);

        Storage::disk('public')->put('complaint_attachments/proof.jpg', 'legacy-file');

        $pending = CustomerComplaint::query()->create([
            'user_id' => $customer->id,
            'account_number' => '501900230',
            'name' => 'Legacy Customer',
            'contact' => '09981234567',
            'complaint' => 'Nganong daku man akong bill this month?',
            'attachment' => 'complaint_attachments/proof.jpg',
            'status' => 'Pending',
            'reference_number' => 'CR-2026-000099',
            'priority' => 'Normal',
        ]);
        $pending->created_at = now()->subDays(10);
        $pending->save();

        $resolved = CustomerComplaint::query()->create([
            'user_id' => $customer->id,
            'account_number' => '551100950',
            'name' => 'Legacy Customer',
            'contact' => '09980000000',
            'complaint' => 'brownout here sa P7, Alegria',
            'status' => 'Resolved',
            'reference_number' => 'CR-2026-000001',
            'priority' => 'Normal',
        ]);

        $stats = app(LegacyComplaintImportService::class)->import();

        $this->assertSame(2, $stats['imported']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertSame(0, $stats['failed']);

        $open = Ticket::query()->where('created_by', LegacyComplaintImportService::createdByKey($pending->id))->firstOrFail();
        $this->assertSame(Ticket::STATUS_NEW, $open->status);
        $this->assertSame($customer->id, $open->customer_id);
        $this->assertSame('app', $open->channel);
        $this->assertSame('CR-2026-000099', $open->subcategory);
        $this->assertSame('normal', $open->priority);
        $this->assertNull($open->assigned_to);
        $this->assertSame('Nganong daku man akong bill this month?', $open->description);
        $this->assertStringNotContainsString('Imported from legacy complaint', $open->description);
        $this->assertStringNotContainsString('Account:', $open->description);
        $this->assertTrue($open->created_at->equalTo($pending->fresh()->created_at));
        $this->assertSame(
            TicketCategory::query()->where('department_code', 'CCAD')->value('id'),
            $open->category_id
        );

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $open->id,
            'uploaded_by' => $customer->id,
        ]);
        Storage::disk('local')->assertExists(TicketAttachment::query()->where('ticket_id', $open->id)->value('file_path'));

        $closed = Ticket::query()->where('created_by', LegacyComplaintImportService::createdByKey($resolved->id))->firstOrFail();
        $this->assertSame(Ticket::STATUS_RESOLVED, $closed->status);
        $this->assertSame(
            TicketCategory::query()->where('department_code', 'COMD')->value('id'),
            $closed->category_id
        );

        $again = app(LegacyComplaintImportService::class)->import();
        $this->assertSame(0, $again['imported']);
        $this->assertSame(2, $again['skipped']);
        $this->assertSame(2, Ticket::query()->where('created_by', 'like', 'legacy-complaint:%')->count());
    }

    public function test_creates_placeholder_customer_when_user_is_missing(): void
    {
        CustomerComplaint::query()->create([
            'account_number' => '123456789',
            'name' => 'Walk-in Guest',
            'contact' => null,
            'complaint' => 'Please add the due date on the dashboard.',
            'status' => 'Pending',
        ]);

        $stats = app(LegacyComplaintImportService::class)->import();

        $this->assertSame(1, $stats['imported']);
        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame('Walk-in Guest', $ticket->customer->name);
        $this->assertSame(
            TicketCategory::query()->where('department_code', 'ISD-CCSMDD')->value('id'),
            $ticket->category_id
        );
    }
}
