<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketCategory;

class TicketUi
{
    /**
     * Customer-friendly labels matching the mobile "Report a Concern" screen.
     *
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'TSD' => 'Power Interruption - Transmission/Substation',
            'COMD' => 'Power Interruption - Distribution Line',
            'CCAD' => 'Billing/Payment Concern',
            'AO-CDS' => 'Meter/New Connection Application',
            'ISD-CCSMDD' => 'Other Institutional Request',
            'FOCAL' => 'Other Concern',
        ];
    }

    public static function categoryLabel(?TicketCategory $category): string
    {
        if ($category === null) {
            return '—';
        }

        return self::categoryLabels()[$category->department_code] ?? $category->name;
    }

    /**
     * Short meaning for each ticket department code.
     *
     * @return array<string, string>
     */
    public static function departmentMeanings(): array
    {
        return [
            'TSD' => 'Technical Services — power interruption on transmission / substation',
            'COMD' => 'Construction & Maintenance — power interruption on distribution lines (daytime)',
            'CCAD' => 'Consumer Accounts — billing and payment concerns',
            'AO-CDS' => 'Area Office CDS — meter and new connection applications',
            'ISD-CCSMDD' => 'Institutional Services — other institutional requests',
            'FOCAL' => 'Focal desk — other / uncategorized concerns',
            'GUARD' => 'Night guard — distribution line interruptions after hours',
            'AREA-ADMIN' => 'Area Administrator — second-tier escalation',
        ];
    }

    public static function departmentMeaning(?string $code): ?string
    {
        $code = trim((string) $code);

        return $code !== '' ? (self::departmentMeanings()[$code] ?? null) : null;
    }

    public static function departmentOptionLabel(?string $code): string
    {
        $code = trim((string) $code);
        $meaning = self::departmentMeaning($code);

        return $meaning ? $code.' ('.$meaning.')' : $code;
    }

    public static function departmentTone(?string $code): string
    {
        return match (trim((string) $code)) {
            'TSD' => 'orange',
            'COMD' => 'amber',
            'CCAD' => 'cyan',
            'AO-CDS' => 'slate',
            'ISD-CCSMDD' => 'indigo',
            'FOCAL' => 'purple',
            'GUARD' => 'rose',
            'AREA-ADMIN' => 'violet',
            default => $code ? KnowledgeUi::toneFromKey((string) $code) : 'neutral',
        };
    }

    public static function departmentIcon(?string $code): string
    {
        return match (trim((string) $code)) {
            'TSD' => 'bi-lightning-charge',
            'COMD' => 'bi-lightbulb',
            'CCAD' => 'bi-receipt',
            'AO-CDS' => 'bi-speedometer2',
            'ISD-CCSMDD' => 'bi-building',
            'FOCAL' => 'bi-chat-left-text',
            'GUARD' => 'bi-shield-shaded',
            'AREA-ADMIN' => 'bi-person-badge',
            default => 'bi-diagram-3',
        };
    }

    /**
     * Intake “How it routes” card: explanation plus flowchart nodes.
     *
     * @return array{explain: string, flow: list<array<string, mixed>>}
     */
    public static function departmentRouteGuide(?string $code, ?int $slaMinutes = null): array
    {
        $code = trim((string) $code);
        $sla = $slaMinutes ? ' SLA '.$slaMinutes.' min.' : '';

        $guides = [
            'TSD' => 'Transmission, sub-transmission, or substation interruptions go straight to Technical Services. The crew works the ticket, then you verify with the customer.',
            'COMD' => 'Distribution line interruptions. Day (06:00–18:00) endorses to COMD. Night (18:00–06:00) goes to GUARD so someone is on it after hours. Then the crew works it and you verify.',
            'CCAD' => 'Billing and payment concerns go to Consumer Accounts. Staff checks the account or payment, then you confirm the customer is settled.',
            'AO-CDS' => 'Meter issues and new-connection applications go to Area Office CDS. Field or office staff handle the request, then you verify with the customer.',
            'ISD-CCSMDD' => 'Institutional or office requests (access, documents, other internal services) go to ISD-CCSMDD. After they act, you close or reopen.',
            'FOCAL' => 'Uncategorized or cross-department concerns go to the focal desk. They handle it or hand it off, then you verify with the customer.',
        ];

        $middle = $code === 'COMD'
            ? [
                ['kind' => 'decision', 'label' => 'Day or night?'],
                ['kind' => 'split', 'branches' => [
                    ['kind' => 'dept', 'label' => 'COMD', 'hint' => '06:00–18:00'],
                    ['kind' => 'dept', 'label' => 'GUARD', 'hint' => '18:00–06:00'],
                ]],
            ]
            : [
                ['kind' => 'dept', 'label' => $code !== '' ? $code : 'Department'],
            ];

        return [
            'explain' => ($guides[$code] ?? (self::departmentMeaning($code) ?: 'Routes to this department.')).$sla,
            'flow' => array_merge(
                [['kind' => 'start', 'label' => 'CSR intake']],
                $middle,
                [
                    ['kind' => 'work', 'label' => 'Staff works'],
                    ['kind' => 'decision', 'label' => 'CSR verify'],
                    ['kind' => 'split', 'branches' => [
                        ['kind' => 'end', 'label' => 'Close'],
                        ['kind' => 'reopen', 'label' => 'Reopen'],
                    ]],
                ],
            ),
        ];
    }

    public static function roleTone(?string $role): string
    {
        return match ((string) $role) {
            'Administrator', 'administrator', 'super_admin', 'Super Administrator' => 'indigo',
            'Customer Service', 'csr', 'Customer Service Representative' => 'sky',
            'support', 'department_staff', 'billing_staff', 'technical_staff', 'Department Staff', 'Billing Staff', 'Technical Staff' => 'teal',
            'Supervisor', 'supervisor', 'support_manager', 'Support Manager' => 'orange',
            'Content Manager', 'content_manager' => 'violet',
            'User', 'customer', 'readonly_staff', 'Read-Only Staff' => 'slate',
            default => $role ? KnowledgeUi::toneFromKey((string) $role) : 'neutral',
        };
    }

    public static function roleLabel(?string $role): string
    {
        $role = trim((string) $role);
        if ($role === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($role, 0, 1)).mb_substr($role, 1);
    }

    public static function roleMeaning(?string $role): ?string
    {
        return match ((string) $role) {
            'support' => 'Frontline ticket and chat support',
            'Customer Service', 'csr', 'Customer Service Representative' => 'CSR intake, verification, and customer follow-up',
            'Supervisor', 'supervisor' => 'Oversees department tickets and assignments',
            'support_manager', 'Support Manager' => 'Manages support staff and department queues',
            'Administrator', 'administrator' => 'Full access to users, tickets, and routing',
            'super_admin', 'Super Administrator' => 'Full system access — Super Administrator',
            'Content Manager', 'content_manager' => 'Manages knowledge base and published content',
            'department_staff', 'Department Staff' => 'Handles tickets assigned to their department',
            'billing_staff', 'Billing Staff' => 'Handles billing and payment ticket work',
            'technical_staff', 'Technical Staff' => 'Handles technical and outage ticket work',
            'readonly_staff', 'Read-Only Staff' => 'View-only access to assigned department tickets',
            'User', 'customer' => 'Regular customer or consumer account',
            default => null,
        };
    }

    public static function roleOptionLabel(?string $role): string
    {
        $label = self::roleLabel($role);
        $meaning = self::roleMeaning($role);

        return $meaning ? $label.' ('.$meaning.')' : $label;
    }

    public static function roleIcon(?string $role): string
    {
        return match ((string) $role) {
            'Administrator', 'administrator', 'super_admin', 'Super Administrator' => 'bi-shield-lock',
            'Customer Service', 'csr', 'Customer Service Representative', 'support' => 'bi-headset',
            'Supervisor', 'supervisor', 'support_manager', 'Support Manager' => 'bi-person-badge',
            'Content Manager', 'content_manager' => 'bi-journal-text',
            'department_staff', 'Department Staff', 'billing_staff', 'Billing Staff', 'technical_staff', 'Technical Staff' => 'bi-headset',
            'User', 'customer', 'readonly_staff', 'Read-Only Staff' => 'bi-person',
            default => 'bi-person',
        };
    }

    /**
     * Shared option colors/icons for custom select UI.
     *
     * @return array<string, array{tone: string, icon: string}>
     */
    public static function optionMeta(): array
    {
        $map = [];

        foreach (array_keys(self::departmentMeanings()) as $code) {
            $map[$code] = ['tone' => self::departmentTone($code), 'icon' => self::departmentIcon($code)];
        }

        foreach ([
            'Administrator', 'administrator', 'super_admin', 'Super Administrator',
            'support', 'Customer Service', 'csr', 'Customer Service Representative',
            'Supervisor', 'supervisor', 'support_manager', 'Support Manager',
            'Content Manager', 'content_manager',
            'department_staff', 'Department Staff', 'billing_staff', 'Billing Staff',
            'technical_staff', 'Technical Staff', 'readonly_staff', 'Read-Only Staff',
            'User', 'customer',
        ] as $role) {
            $map[$role] = ['tone' => self::roleTone($role), 'icon' => self::roleIcon($role)];
        }

        foreach (array_keys(self::statusLabels()) as $status) {
            $map[$status] = ['tone' => self::statusTone($status), 'icon' => self::statusIcon($status)];
            $map[self::statusLabel($status)] = $map[$status];
        }

        foreach (['urgent', 'high', 'normal', 'low'] as $priority) {
            $map[$priority] = ['tone' => self::priorityTone($priority), 'icon' => 'bi-bar-chart'];
            $map[ucfirst($priority)] = $map[$priority];
        }

        foreach (self::channels() as $channel) {
            $map[$channel] = ['tone' => 'slate', 'icon' => self::channelIcon($channel)];
        }

        $map['active'] = $map['Active'] = $map['Activate'] = ['tone' => 'lime', 'icon' => 'bi-check-circle-fill'];
        $map['Deactivate'] = ['tone' => 'slate', 'icon' => 'bi-pause-circle'];
        $map['Bulk status…'] = $map['Bulk status...'] = ['tone' => 'slate', 'icon' => 'bi-sliders'];
        $map['Assign department…'] = $map['Assign department...'] = ['tone' => 'slate', 'icon' => 'bi-diagram-3'];
        $map['Assign role…'] = $map['Assign role...'] = ['tone' => 'slate', 'icon' => 'bi-person-badge'];
        $map['draft'] = $map['Draft'] = ['tone' => 'amber', 'icon' => 'bi-pencil-square'];
        $map['inactive'] = $map['Inactive'] = ['tone' => 'slate', 'icon' => 'bi-pause-circle'];
        $map['Yes'] = $map['Verified'] = ['tone' => 'lime', 'icon' => 'bi-check-circle'];
        $map['No'] = $map['Not verified'] = ['tone' => 'rose', 'icon' => 'bi-x-circle'];
        $map['All'] = $map['All support roles'] = $map['All departments'] = ['tone' => 'slate', 'icon' => 'bi-collection'];
        $map['No department'] = $map['Unassigned'] = ['tone' => 'slate', 'icon' => 'bi-dash-circle'];
        $map['Select role'] = ['tone' => 'slate', 'icon' => 'bi-person-badge'];
        $map['Support'] = ['tone' => 'indigo', 'icon' => 'bi-headset'];
        $map['Customer'] = ['tone' => 'sky', 'icon' => 'bi-person'];
        foreach (['available', 'Available'] as $key) {
            $map[$key] = ['tone' => 'lime', 'icon' => 'bi-person-check'];
        }
        foreach (['busy', 'Busy'] as $key) {
            $map[$key] = ['tone' => 'orange', 'icon' => 'bi-exclamation-circle'];
        }
        foreach (['away', 'Away'] as $key) {
            $map[$key] = ['tone' => 'amber', 'icon' => 'bi-person-dash'];
        }
        foreach (['offline', 'Offline'] as $key) {
            $map[$key] = ['tone' => 'slate', 'icon' => 'bi-person-x'];
        }
        foreach (['on_leave', 'On leave'] as $key) {
            $map[$key] = ['tone' => 'violet', 'icon' => 'bi-calendar-x'];
        }
        foreach (['pending_activation', 'Pending activation'] as $key) {
            $map[$key] = ['tone' => 'amber', 'icon' => 'bi-hourglass'];
        }
        foreach (['suspended', 'Suspended'] as $key) {
            $map[$key] = ['tone' => 'orange', 'icon' => 'bi-slash-circle'];
        }
        foreach (['locked', 'Locked'] as $key) {
            $map[$key] = ['tone' => 'rose', 'icon' => 'bi-lock'];
        }
        $map['Auto (round-robin in department)'] = $map['Auto (round-robin)'] = ['tone' => 'sky', 'icon' => 'bi-arrow-repeat'];
        $map['Open (default)'] = ['tone' => 'slate', 'icon' => 'bi-inbox'];
        $map['All departments'] = ['tone' => 'slate', 'icon' => 'bi-diagram-3'];
        $map['Select category'] = ['tone' => 'slate', 'icon' => 'bi-tag'];
        $map['None — not ticket support'] = ['tone' => 'slate', 'icon' => 'bi-dash-circle'];
        $map['Select duration'] = ['tone' => 'slate', 'icon' => 'bi-clock'];
        $map['custom'] = $map['Custom time'] = ['tone' => 'violet', 'icon' => 'bi-pencil-square'];

        foreach (self::minutesOptions() as $minutes => $label) {
            $map[(string) $minutes] = $map[$label] = ['tone' => 'sky', 'icon' => 'bi-clock'];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public static function minutesOptions(): array
    {
        return [
            5 => '5 minutes',
            10 => '10 minutes',
            15 => '15 minutes',
            20 => '20 minutes',
            30 => '30 minutes',
            45 => '45 minutes',
            60 => '1 hour',
            90 => '1 hour 30 minutes',
            120 => '2 hours',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            Ticket::STATUS_NEW => 'Submitted',
            Ticket::STATUS_ENDORSED => 'Endorsed',
            Ticket::STATUS_ASSIGNED => 'Assigned',
            Ticket::STATUS_IN_PROGRESS => 'In Progress',
            Ticket::STATUS_AWAITING_FEEDBACK => 'Awaiting Feedback',
            Ticket::STATUS_ESCALATED => 'Escalated',
            Ticket::STATUS_RESOLVED => 'Resolved',
            Ticket::STATUS_CLOSED => 'Closed',
            Ticket::STATUS_REOPENED => 'Reopened',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusLabels()[$status] ?? ucfirst((string) $status);
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            Ticket::STATUS_NEW => 'bg-secondary',
            Ticket::STATUS_ENDORSED, Ticket::STATUS_ASSIGNED => 'bg-info',
            Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_REOPENED => 'bg-primary',
            Ticket::STATUS_AWAITING_FEEDBACK => 'bg-warning text-dark',
            Ticket::STATUS_ESCALATED => 'bg-danger',
            Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED => 'bg-success',
            default => 'bg-light text-dark border',
        };
    }

    public static function statusTone(?string $status): string
    {
        return match ($status) {
            Ticket::STATUS_NEW => 'slate',
            Ticket::STATUS_ENDORSED => 'sky',
            Ticket::STATUS_ASSIGNED => 'indigo',
            Ticket::STATUS_IN_PROGRESS => 'teal',
            Ticket::STATUS_AWAITING_FEEDBACK => 'amber',
            Ticket::STATUS_ESCALATED => 'rose',
            Ticket::STATUS_RESOLVED => 'lime',
            Ticket::STATUS_CLOSED => 'success',
            Ticket::STATUS_REOPENED => 'orange',
            default => 'neutral',
        };
    }

    public static function statusIcon(?string $status): string
    {
        return match ($status) {
            Ticket::STATUS_NEW => 'bi-inbox',
            Ticket::STATUS_ENDORSED => 'bi-send',
            Ticket::STATUS_ASSIGNED => 'bi-person-check',
            Ticket::STATUS_IN_PROGRESS => 'bi-arrow-repeat',
            Ticket::STATUS_AWAITING_FEEDBACK => 'bi-chat-dots',
            Ticket::STATUS_ESCALATED => 'bi-exclamation-triangle',
            Ticket::STATUS_RESOLVED => 'bi-check-circle',
            Ticket::STATUS_CLOSED => 'bi-lock',
            Ticket::STATUS_REOPENED => 'bi-arrow-counterclockwise',
            default => 'bi-circle',
        };
    }

    /**
     * Linear ticket path. Branches (escalated / reopened) are inserted only when they happen.
     *
     * @return list<string>
     */
    public static function timelinePath(): array
    {
        return [
            Ticket::STATUS_NEW,
            Ticket::STATUS_ENDORSED,
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_AWAITING_FEEDBACK,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
        ];
    }

    /**
     * Predefined ticket statuses with reached / current / pending state.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    public static function timelineTrack(Ticket $ticket, array $events = []): array
    {
        $reached = [$ticket->status => true];
        $latestAt = [];
        $latestActor = [];
        $history = $ticket->relationLoaded('statusHistory')
            ? $ticket->statusHistory
            : ($ticket->exists ? $ticket->statusHistory : collect());

        foreach ($history as $row) {
            foreach ([$row->from_status, $row->to_status] as $status) {
                if (is_string($status) && $status !== '') {
                    $reached[$status] = true;
                }
            }
            if (is_string($row->to_status) && $row->to_status !== '') {
                $latestAt[$row->to_status] = $row->created_at;
                $latestActor[$row->to_status] = $row->actor?->name;
            }
        }

        if (! isset($latestAt[Ticket::STATUS_NEW])) {
            $latestAt[Ticket::STATUS_NEW] = $ticket->created_at;
        }

        $eventsByStatus = [];
        foreach ($events as $event) {
            $status = $event['status'] ?? null;
            if (is_string($status) && $status !== '') {
                $eventsByStatus[$status][] = $event;
            }
        }

        $path = self::timelinePath();
        $indexByStatus = array_flip($path);
        $current = (string) $ticket->status;
        $cursor = $indexByStatus[$current] ?? null;
        if ($current === Ticket::STATUS_ESCALATED) {
            $cursor = $indexByStatus[Ticket::STATUS_ASSIGNED] ?? 2;
        } elseif ($current === Ticket::STATUS_REOPENED) {
            $cursor = $indexByStatus[Ticket::STATUS_IN_PROGRESS] ?? 3;
        }

        $ordered = $path;
        if (isset($reached[Ticket::STATUS_ESCALATED])) {
            array_splice($ordered, ($indexByStatus[Ticket::STATUS_ASSIGNED] ?? 2) + 1, 0, [Ticket::STATUS_ESCALATED]);
        }
        if (isset($reached[Ticket::STATUS_REOPENED])) {
            $insertAt = array_search(Ticket::STATUS_IN_PROGRESS, $ordered, true);
            array_splice($ordered, $insertAt === false ? count($ordered) : $insertAt + 1, 0, [Ticket::STATUS_REOPENED]);
        }

        $steps = [];
        foreach ($ordered as $status) {
            $isCurrent = $current === $status;
            $pathIndex = $indexByStatus[$status] ?? null;
            $isPrior = $cursor !== null && $pathIndex !== null && $pathIndex < $cursor;
            $isReached = $isCurrent || $isPrior || isset($reached[$status]);

            if ($isCurrent) {
                $state = 'current';
            } elseif ($isPrior || (isset($reached[$status]) && $pathIndex !== null && $cursor !== null && $pathIndex < $cursor)) {
                $state = 'done';
            } elseif (isset($reached[$status]) && in_array($status, [Ticket::STATUS_ESCALATED, Ticket::STATUS_REOPENED], true) && ! $isCurrent) {
                $state = 'done';
            } else {
                $state = 'pending';
            }

            $steps[] = [
                'status' => $status,
                'label' => self::statusLabel($status),
                'icon' => self::statusIcon($status),
                'tone' => self::statusTone($status),
                'state' => $state,
                'at' => $latestAt[$status] ?? null,
                'actor' => $latestActor[$status] ?? null,
                'events' => $isReached ? ($eventsByStatus[$status] ?? []) : [],
            ];
        }

        return $steps;
    }

    public static function categoryIcon(?TicketCategory $category): string
    {
        return self::departmentIcon($category?->department_code);
    }

    public static function categoryTone(?TicketCategory $category): string
    {
        if (! filled($category?->department_code)) {
            return KnowledgeUi::toneFromKey((string) ($category?->name ?: 'ticket'));
        }

        return self::departmentTone($category->department_code);
    }

    public static function channelIcon(?string $channel): string
    {
        return match ($channel) {
            'call' => 'bi-telephone',
            'text', 'social/sms' => 'bi-chat-text',
            'app' => 'bi-phone',
            default => 'bi-broadcast',
        };
    }

    public static function priorityTone(?string $priority): string
    {
        return match ($priority) {
            'urgent' => 'rose',
            'high' => 'orange',
            'normal' => 'sky',
            'low' => 'slate',
            default => 'neutral',
        };
    }

    public static function sentimentTone(?string $sentiment): string
    {
        $value = strtolower((string) $sentiment);

        return match (true) {
            str_contains($value, 'neg') || str_contains($value, 'angry') || str_contains($value, 'frust') || str_contains($value, 'distress') => 'rose',
            str_contains($value, 'pos') || str_contains($value, 'satisf') => 'lime',
            default => 'slate',
        };
    }

    /**
     * @return list<string>
     */
    public static function channels(): array
    {
        return ['call', 'text', 'social/sms', 'app'];
    }

    public static function richHtml(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (! preg_match('/<\/?[a-z][\s\S]*>/i', $value)) {
            return nl2br(e($value), false);
        }

        $clean = preg_replace('/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/i', '', $value) ?? $value;
        $clean = strip_tags($clean, '<p><br><b><strong><i><em><u><ul><ol><li><s><div>');
        $clean = preg_replace('/<(\/?)([a-z0-9]+)[^>]*>/i', '<$1$2>', $clean) ?? '';

        return $clean;
    }
}
