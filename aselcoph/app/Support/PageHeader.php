<?php

namespace App\Support;

class PageHeader
{
    public static function subtitle(mixed $explicit = null, mixed $title = null, mixed $active = null): string
    {
        $explicit = self::plain($explicit);
        if (self::isDescriptive($explicit)) {
            return $explicit;
        }

        $mapped = self::byRoute(request()->route()?->getName())
            ?? self::byPath(request()->path());
        if ($mapped !== null) {
            return $mapped;
        }

        $activeText = self::plain($active);
        if (self::isDescriptive($activeText)) {
            return $activeText;
        }

        return self::fromTitle($title, $active);
    }

    private static function byRoute(?string $name): ?string
    {
        return match ($name) {
            'dashboard' => 'A snapshot of current operations, recent activity, and shortcuts for your role.',
            'profile.show' => 'Update your profile, password, and security options for this staff portal.',
            'tickets.queue' => 'Review, assign, and process open customer tickets from the working queue.',
            'tickets.intake' => 'Log a customer concern received by call, text, social media, or walk-in.',
            'tickets.escalations' => 'Track tickets raised for higher-level review and follow them through to a response.',
            'tickets.reports' => 'Filter ticket activity, inspect trends, and export a CSV of the selected period.',
            'workspace.department' => 'Open tickets, department workload, and period reports in one place.',
            'tickets.ai' => 'Advisory AI metrics for ticket handling. Routing, SLA, and authorization stay with staff.',
            'tickets.show' => 'Review this ticket’s timeline, SLA, and history, then take the next staff action.',
            'knowledge.dashboard' => 'Monitor knowledge coverage, document health, and retrieval quality for the assistant.',
            'knowledge.index' => 'Browse, upload, and maintain documents used to answer customer questions.',
            'knowledge.create' => 'Upload or write new knowledge so the assistant can retrieve approved answers.',
            'knowledge.edit' => 'Update this document so staff and the assistant keep using the latest approved content.',
            'knowledge.show' => 'Inspect this document, its status, and how it is indexed for retrieval.',
            'knowledge.categories' => 'Organize knowledge into categories so staff and the assistant can find it faster.',
            'knowledge.test' => 'Run retrieval on its own to check ranking quality before relying on live chat.',
            'knowledge.chat' => 'Chat with the live assistant against approved knowledge. Replies show citations and do not create tickets.',
            'ast.admin.dashboard' => 'Watch wallet balances, load activity, and pending approvals for ASELCO Token.',
            'ast.admin.load' => 'Credit, reduce, or set a customer AST balance. Large loads may require a second staff approval.',
            'ast.admin.request' => 'Ask a wallet loader to credit AST. Support submits the request; Load wallets staff approve it.',
            'ast.admin.load-requests' => 'Review support load requests and approve or reject pending credits.',
            'ast.admin.customer-wallet' => 'Inspect this member’s AST balance, recent loads, and payment activity.',
            'ast.wallet.show' => 'Review AST wallet balances and complete the next load or inquiry for a member.',
            'ast.cis.queue' => 'Mark AST bill payments as posted after they are entered in the official CIS ledger.',
            'announcements.index' => 'Create and publish mobile announcements for members and selected audiences.',
            'announcements.create' => 'Compose a mobile announcement and choose who should receive it.',
            'announcements.edit' => 'Update this draft announcement before you publish it.',
            'announcements.show' => 'Review this announcement, its audience, and whether it has been published.',
            'chats.support' => 'Live customer support chat with realtime delivery over Pusher.',
            'calendar.view' => 'Plan and review cooperative calendar activities for the selected period.',
            'billing.upload.create' => 'Upload billing files, confirm what was received, and keep records ready for processing.',
            'filemanager.index' => 'Browse stored files, open a preview, and keep documents organized for staff use.',
            'folder.preview.file' => 'Open this file, confirm its contents, and return to the library when you are done.',
            'menus.index' => 'Create and arrange portal menus so staff can reach the right pages quickly.',
            'menus.builder' => 'Build this menu’s items, order, and links so navigation stays clear for staff.',
            'chats.show', 'chats.monitor', 'conversations.show' => 'Respond to member conversations and keep support threads moving to resolution.',
            'supp.chat' => 'Handle customer support messages, reply in thread, and keep each case moving.',
            default => null,
        };
    }

    private static function byPath(?string $path): ?string
    {
        $path = trim((string) $path, '/');

        return match (true) {
            $path === 'u/dashboard', $path === 'dashboard' => 'A snapshot of current operations, recent activity, and shortcuts for your role.',
            $path === 't/dashboard' => 'See billing activity, pending uploads, and the work that needs attention today.',
            $path === 'validation' => 'Review pending account validations, confirm details, and approve or return items that need work.',
            $path === 'billing-upload' => 'Upload billing files, confirm what was received, and keep records ready for processing.',
            $path === 'consumer/list' => 'Look up member records, linked accounts, and related service information.',
            $path === 'customer/registration' => 'Review new account requests, confirm member details, and complete registration.',
            $path === 'complaint', $path === 'complaints/create' => 'Review customer complaints, update their status, and keep a clear record of follow-up.',
            $path === 'users', str_starts_with($path, 'access/customers') => 'Look up portal members, membership applications, linked accounts, and related service information.',
            str_starts_with($path, 'access/') => 'Create and manage staff accounts, roles, departments, and access for this portal.',
            str_starts_with($path, 'workspace/') => 'Your assigned tickets, department queue, and staff notifications.',
            $path === 'ublog', str_starts_with($path, 'ublog/') => 'Draft, edit, and publish articles that members and the public can read.',
            $path === 'google-drive' => 'Upload files to Google Drive and keep cooperative documents in one place.',
            $path === 'support' => 'Respond to member conversations and keep support threads moving to resolution.',
            $path === 'pages', $path === 'pages/new' => 'Create and arrange portal pages so staff can reach the right content quickly.',
            default => null,
        };
    }

    private static function fromTitle(mixed $title, mixed $active): string
    {
        $heading = self::plain($title);
        $status = self::plain($active);
        $haystack = mb_strtolower($heading.' '.$status);

        if (str_contains($haystack, 'queue')) {
            return 'Review open items, prioritize work, and move each request to the next step.';
        }
        if (str_contains($haystack, 'dashboard')) {
            return 'See current activity, key counts, and shortcuts for this workspace.';
        }
        if (str_contains($haystack, 'report') || str_contains($haystack, 'analytics') || str_contains($haystack, 'insight')) {
            return 'Explore activity for this area, apply filters, and export results when needed.';
        }
        if (str_contains($haystack, 'upload')) {
            return 'Upload files, confirm what was received, and keep records ready for processing.';
        }
        if (str_contains($haystack, 'validation')) {
            return 'Review pending validations, confirm details, and approve or return items that need work.';
        }
        if (str_contains($haystack, 'user management') || str_contains($haystack, 'register users')) {
            return 'Create and manage staff accounts, roles, and access for this portal.';
        }
        if (str_contains($haystack, 'profile') || str_contains($haystack, 'setting')) {
            return 'Update your profile, security options, and preferences for this portal.';
        }
        if (str_contains($haystack, 'complaint')) {
            return 'Review customer complaints, update their status, and keep a clear record of follow-up.';
        }
        if (str_contains($haystack, 'file') || str_contains($haystack, 'storage') || str_contains($haystack, 'preview')) {
            return 'Browse stored files, open a preview, and keep documents organized for staff use.';
        }
        if (str_contains($haystack, 'menu')) {
            return 'Create and arrange portal menus so staff can reach the right pages quickly.';
        }
        if (str_contains($haystack, 'calendar')) {
            return 'Schedule, review, and update cooperative activities on the shared calendar.';
        }
        if (str_contains($haystack, 'announcement')) {
            return 'Draft, review, and publish announcements that members will see on mobile.';
        }
        if (str_contains($haystack, 'chat') || str_contains($haystack, 'support')) {
            return 'Respond to member conversations and keep support threads moving to resolution.';
        }
        if (str_contains($haystack, 'consumer') || str_contains($haystack, 'customer')) {
            return 'Look up member records, linked accounts, and related service information.';
        }
        if (str_contains($haystack, 'wallet') || str_contains($haystack, 'ast') || str_contains($haystack, 'load')) {
            return 'Review AST wallet activity and complete the next load or approval step.';
        }
        if (str_contains($haystack, 'knowledge') || str_contains($haystack, 'document')) {
            return 'Keep approved knowledge current so staff and the assistant can retrieve the right answers.';
        }
        if (str_contains($haystack, 'ticket')) {
            return 'Follow this ticket’s history and take the next action needed to resolve it.';
        }
        if (str_contains($haystack, 'welcome')) {
            return 'Start from an overview of cooperative activity and jump into the work that needs attention.';
        }
        if (str_contains($haystack, 'account')) {
            return 'Review account records, confirm details, and complete the next staff action.';
        }

        if ($heading !== '') {
            return 'Review the details for '.$heading.' and take the next action from this page.';
        }

        return 'Review the information on this page and take the next action.';
    }

    private static function isDescriptive(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return str_word_count($text) >= 4 || mb_strlen($text) >= 28;
    }

    private static function plain(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
    }
}
