<?php

namespace App\Support;

class AccessUi
{
    public static function accountStatusTone(?string $status): string
    {
        return match ($status) {
            'active' => 'lime',
            'pending_activation' => 'amber',
            'inactive' => 'slate',
            'suspended' => 'orange',
            'locked' => 'rose',
            default => 'neutral',
        };
    }

    public static function accountStatusIcon(?string $status): string
    {
        return match ($status) {
            'active' => 'bi-check-circle',
            'pending_activation' => 'bi-hourglass',
            'inactive' => 'bi-pause-circle',
            'suspended' => 'bi-slash-circle',
            'locked' => 'bi-lock',
            default => 'bi-circle',
        };
    }

    public static function accountStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'pending_activation' => 'Pending activation',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
            'locked' => 'Locked',
            default => $status ? str_replace('_', ' ', $status) : 'Active',
        };
    }

    public static function availabilityTone(?string $status): string
    {
        return match ($status) {
            'available' => 'lime',
            'busy' => 'orange',
            'away' => 'amber',
            'on_leave' => 'violet',
            'offline' => 'slate',
            default => 'neutral',
        };
    }

    public static function availabilityIcon(?string $status): string
    {
        return match ($status) {
            'available' => 'bi-person-check',
            'busy' => 'bi-exclamation-circle',
            'away' => 'bi-person-dash',
            'on_leave' => 'bi-calendar-x',
            'offline' => 'bi-person-x',
            default => 'bi-person',
        };
    }

    public static function departmentOptionLabel(?string $code, ?string $fallbackName = null): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        $meaning = TicketUi::departmentMeaning($code) ?: trim((string) $fallbackName);

        return $meaning !== '' ? $code.' ('.$meaning.')' : $code;
    }

    public static function roleOptionLabel(?string $code, ?string $name = null): string
    {
        $code = trim((string) $code);
        $name = trim((string) $name);
        $title = $name !== '' ? $name : TicketUi::roleLabel($code);
        $meaning = TicketUi::roleMeaning($code) ?: TicketUi::roleMeaning($name);

        if ($title === '') {
            return '';
        }

        return $meaning ? $title.' ('.$meaning.')' : $title;
    }

    public static function roleKey(?string $code, ?string $name = null): string
    {
        return trim((string) ($code ?: $name));
    }

    public static function availabilityLabel(?string $status): string
    {
        return match ($status) {
            'available' => 'Available',
            'busy' => 'Busy',
            'away' => 'Away',
            'on_leave' => 'On leave',
            'offline' => 'Offline',
            default => $status ? str_replace('_', ' ', $status) : 'Offline',
        };
    }

    public static function userTypeTone(?string $type): string
    {
        return $type === 'customer' ? 'sky' : 'indigo';
    }

    public static function userTypeLabel(?string $type): string
    {
        return $type === 'customer' ? 'Customer' : 'Support';
    }

    public static function scopeTone(?string $scope): string
    {
        return match ($scope) {
            'all' => 'indigo',
            'department' => 'teal',
            'assigned' => 'sky',
            'own' => 'slate',
            default => 'neutral',
        };
    }

    public static function scopeLabel(?string $scope): string
    {
        return match ($scope) {
            'all' => 'All',
            'department' => 'Department',
            'assigned' => 'Assigned',
            'own' => 'Own',
            default => $scope ? ucfirst($scope) : '—',
        };
    }

    public static function label(?string $value, string $empty = '—'): string
    {
        $value = trim((string) $value);

        return $value === '' ? $empty : str_replace('_', ' ', $value);
    }
}
