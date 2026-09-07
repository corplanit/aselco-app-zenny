<?php

return [
    'assignment' => [
        'strategy' => env('TICKET_AUTO_ASSIGN_STRATEGY', 'round_robin'),
        'day_shift_start_hour' => (int) env('TICKET_DAY_SHIFT_START_HOUR', 6),
        'day_shift_end_hour' => (int) env('TICKET_DAY_SHIFT_END_HOUR', 18),
        'distribution_day_department' => env('TICKET_DISTRIBUTION_DAY_DEPARTMENT', 'COMD'),
        'distribution_night_department' => env('TICKET_DISTRIBUTION_NIGHT_DEPARTMENT', 'GUARD'),
        'tsd_department' => env('TICKET_TSD_DEPARTMENT', 'TSD'),
    ],

    'escalation' => [
        'command_frequency_minutes' => (int) env('TICKET_SLA_SWEEP_MINUTES', 5),
        'second_tier_after_minutes' => (int) env('TICKET_SECOND_TIER_AFTER_MINUTES', 30),
        'second_tier_department' => env('TICKET_SECOND_TIER_DEPARTMENT', 'AREA-ADMIN'),
    ],

    'attachment' => [
        'max_kb' => (int) env('TICKET_ATTACHMENT_MAX_KB', 10240),
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'pdf', 'mp4'],
        'signed_url_ttl_minutes' => (int) env('TICKET_ATTACHMENT_SIGNED_URL_TTL_MINUTES', 15),
        'disk' => env('TICKET_ATTACHMENT_DISK', 'local'),
        'directory' => env('TICKET_ATTACHMENT_DIRECTORY', 'tickets'),
    ],

    'admin_roles' => [
        'Administrator',
        'administrator',
    ],

    'supervisor_roles' => [
        'Supervisor',
        'supervisor',
    ],

    'csr_roles' => [
        'Customer Service',
        'support',
    ],

    'supervisor_department_suffix' => env('TICKET_SUPERVISOR_DEPARTMENT_SUFFIX', '-SUP'),

    /*
    | AI assists tickets but never replaces routing/SLA/authorization.
    | Suggested customer replies require CSR review unless auto-send is enabled
    | for explicitly low-risk drafts (default off).
    */
    'ai' => [
        'enabled' => (bool) env('TICKET_AI_ENABLED', true),
        'auto_analyze_on_create' => (bool) env('TICKET_AI_AUTO_ANALYZE', true),
        'auto_send_low_risk_responses' => (bool) env('TICKET_AI_AUTO_SEND', false),
        'min_confidence_for_auto_send' => (float) env('TICKET_AI_AUTO_SEND_MIN_CONFIDENCE', 0.85),
        'queue' => env('TICKET_AI_QUEUE', 'default'),
    ],
];
