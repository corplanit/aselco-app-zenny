<?php

namespace Tests\Feature;

use App\Models\AstAuditLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Models\WalletAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AppendOnlyAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_audit_log_cannot_be_updated(): void
    {
        $walletLog = WalletAuditLog::query()->create([
            'wallet_id' => null,
            'actor_id' => null,
            'actor_type' => WalletAuditLog::ACTOR_SYSTEM,
            'action' => 'test',
            'ip_address' => '127.0.0.1',
        ]);

        $this->expectException(LogicException::class);
        $walletLog->update(['action' => 'tampered']);
    }

    public function test_ast_audit_log_cannot_be_deleted(): void
    {
        $log = AstAuditLog::query()->create([
            'wallet_id' => null,
            'ledger_entry_id' => null,
            'actor_id' => null,
            'actor_role' => 'system',
            'action' => 'test',
            'amount' => '0.00',
            'balance_before' => '0.00',
            'balance_after' => '0.00',
            'idempotency_key' => 'append-only-test',
            'ip_address' => '10.0.0.1',
        ]);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_ticket_status_history_cannot_be_updated(): void
    {
        $customer = User::factory()->create(['role' => 'User']);
        $category = TicketCategory::query()->firstOrFail();
        $ticket = Ticket::query()->create([
            'ticket_no' => 'TKT-APPEND-1',
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'channel' => 'app',
            'description' => 'append-only check',
            'status' => Ticket::STATUS_NEW,
            'created_by' => 'customer',
        ]);

        $history = TicketStatusHistory::query()->create([
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => Ticket::STATUS_NEW,
            'changed_by' => $customer->id,
            'remarks' => 'created',
            'ip_address' => '192.0.2.1',
        ]);

        $this->expectException(LogicException::class);
        $history->update(['remarks' => 'tampered']);
    }
}
