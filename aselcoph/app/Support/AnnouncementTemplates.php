<?php

namespace App\Support;

class AnnouncementTemplates
{
    /**
     * Ready-to-use announcement drafts for support.
     * Placeholders in [BRACKETS] should be replaced before publish.
     *
     * @return list<array{id: string, label: string, hint: string, category: string, icon: string, tone: string, title: string, body: string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'scheduled-interruption',
                'label' => 'Scheduled interruption',
                'hint' => 'Planned outage with date and time',
                'category' => 'service',
                'icon' => 'bi-calendar-event',
                'tone' => 'lime',
                'title' => 'Scheduled power interruption — [BARANGAY]',
                'body' => "ASELCO will conduct a scheduled power interruption in [BARANGAY], [MUNICIPALITY] on [DATE] from [START TIME] to [END TIME] for [REASON].\n\nPlease prepare accordingly. We apologize for the inconvenience.",
            ],
            [
                'id' => 'emergency-outage',
                'label' => 'Emergency outage',
                'hint' => 'Unplanned interruption in progress',
                'category' => 'service',
                'icon' => 'bi-lightning-charge',
                'tone' => 'lime',
                'title' => 'Power interruption — [BARANGAY]',
                'body' => "ASELCO is responding to a power interruption affecting [BARANGAY], [MUNICIPALITY]. Crews are on site / being dispatched.\n\nRestoration time: [ETA or TO FOLLOW]. Stay clear of downed lines and wait for official updates.",
            ],
            [
                'id' => 'power-restored',
                'label' => 'Power restored',
                'hint' => 'Service is back in the affected area',
                'category' => 'service',
                'icon' => 'bi-plugin',
                'tone' => 'lime',
                'title' => 'Power restored — [BARANGAY]',
                'body' => "Electric service has been restored in [BARANGAY], [MUNICIPALITY] as of [TIME].\n\nIf your household is still without power, please report it through the ASELCO app or contact Customer Service.",
            ],
            [
                'id' => 'line-maintenance',
                'label' => 'Line maintenance',
                'hint' => 'Field work that may affect service',
                'category' => 'service',
                'icon' => 'bi-tools',
                'tone' => 'lime',
                'title' => 'Line maintenance — [AREA]',
                'body' => "ASELCO will conduct line maintenance in [AREA] on [DATE] from [START TIME] to [END TIME].\n\nBrief interruptions may occur. Thank you for your understanding.",
            ],
            [
                'id' => 'bill-available',
                'label' => 'Bill now available',
                'hint' => 'New billing period is ready',
                'category' => 'billing',
                'icon' => 'bi-receipt',
                'tone' => 'amber',
                'title' => 'Your [BILLING MONTH] bill is now available',
                'body' => "Your ASELCO bill for [BILLING MONTH] is now available in the app.\n\nAmount due: [AMOUNT]\nDue date: [DUE DATE]\n\nPlease pay on or before the due date to avoid late charges or disconnection.",
            ],
            [
                'id' => 'due-date-reminder',
                'label' => 'Due date reminder',
                'hint' => 'Payment is coming due',
                'category' => 'billing',
                'icon' => 'bi-calendar-check',
                'tone' => 'amber',
                'title' => 'Reminder: bill due on [DUE DATE]',
                'body' => "This is a reminder that your ASELCO bill is due on [DUE DATE]. Amount due: [AMOUNT].\n\nYou may pay through the official payment channels listed in the app. Please disregard if you have already paid.",
            ],
            [
                'id' => 'disconnection-notice',
                'label' => 'Disconnection notice',
                'hint' => 'Overdue account may be disconnected',
                'category' => 'billing',
                'icon' => 'bi-plug',
                'tone' => 'amber',
                'title' => 'Disconnection notice — settle on or before [DATE]',
                'body' => "Your account remains unpaid. To avoid disconnection, please settle your outstanding balance of [AMOUNT] on or before [DATE].\n\nIf you have already paid, please allow time for posting or present your official receipt to Customer Service.",
            ],
            [
                'id' => 'office-holiday',
                'label' => 'Office holiday',
                'hint' => 'Closed office or holiday hours',
                'category' => 'alert',
                'icon' => 'bi-building',
                'tone' => 'rose',
                'title' => 'Office closed — [HOLIDAY / DATE]',
                'body' => "ASELCO offices will be closed on [DATE] in observance of [HOLIDAY].\n\nRegular office hours resume on [RESUME DATE], [RESUME TIME]. Emergency reports may still be filed through the ASELCO app.",
            ],
            [
                'id' => 'weather-advisory',
                'label' => 'Weather advisory',
                'hint' => 'Typhoon or severe weather notice',
                'category' => 'alert',
                'icon' => 'bi-cloud-rain',
                'tone' => 'rose',
                'title' => 'Weather advisory — [STORM / DATE]',
                'body' => "ASELCO advises members in [AREA] to prepare for [STORM / WEATHER] on [DATE]. Power interruptions may occur if conditions become unsafe.\n\nStay indoors, keep emergency lights ready, and report downed lines only from a safe distance.",
            ],
            [
                'id' => 'safety-advisory',
                'label' => 'Safety advisory',
                'hint' => 'Downed lines or hazardous area',
                'category' => 'alert',
                'icon' => 'bi-exclamation-triangle',
                'tone' => 'rose',
                'title' => 'Safety advisory — [AREA]',
                'body' => "Please stay away from [HAZARD] in [AREA]. Do not touch downed wires, poles, or damaged equipment.\n\nReport the location through the ASELCO app or Customer Service. Wait for official clearance before approaching the area.",
            ],
        ];
    }
}
