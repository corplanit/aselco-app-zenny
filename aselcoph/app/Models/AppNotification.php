<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    public const CATEGORIES = [
        'assignment',
        'escalation',
        'ticket',
        'wallet',
        'billing',
        'knowledge',
        'announcement',
        'complaint',
        'chat',
        'access',
        'service',
        'alert',
        'system',
    ];

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function staffUrl(): string
    {
        $data = is_array($this->data) ? $this->data : [];

        if (filled($data['url'] ?? null) && is_string($data['url'])) {
            return $data['url'];
        }

        try {
            if (filled($data['ticket_id'] ?? null)) {
                return route('tickets.show', $data['ticket_id']);
            }
            if (filled($data['load_request_id'] ?? null)) {
                return route('ast.admin.load-requests', ['status' => 'pending']);
            }
            if (filled($data['announcement_id'] ?? null)) {
                return route('announcements.show', $data['announcement_id']);
            }
            if (filled($data['document_id'] ?? null)) {
                return route('knowledge.show', $data['document_id']);
            }
            if (filled($data['complaint_id'] ?? null)) {
                return url('/complaint');
            }
            if (filled($data['customer_id'] ?? null)) {
                return route('ast.admin.customer-wallet', $data['customer_id']);
            }
        } catch (\Throwable) {
            // Fall through to the inbox when a module route is unavailable.
        }

        $deep = $data['deep_link'] ?? null;
        if (is_string($deep) && str_starts_with($deep, '/tickets/')) {
            $id = trim(str_replace('/tickets/', '', $deep), '/');
            if (ctype_digit($id)) {
                return route('tickets.show', $id);
            }
        }

        return route('workspace.notifications');
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'assignment' => 'Assignment',
            'escalation' => 'Escalation',
            'ticket' => 'Ticket',
            'wallet' => 'AST Wallet',
            'billing' => 'Billing',
            'knowledge' => 'Knowledge',
            'announcement' => 'Announcement',
            'complaint' => 'Complaint',
            'chat' => 'Chat',
            'access' => 'Access',
            'service' => 'Service',
            'system' => 'System',
            default => 'Alert',
        };
    }

    public function categoryTone(): string
    {
        return match ($this->category) {
            'assignment' => 'sky',
            'escalation' => 'rose',
            'ticket' => 'indigo',
            'wallet', 'billing' => 'lime',
            'knowledge' => 'violet',
            'announcement' => 'amber',
            'complaint' => 'orange',
            'chat' => 'cyan',
            default => 'slate',
        };
    }
}
