<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tickets.admin_roles', ['Administrator', 'administrator']);
        config()->set('tickets.supervisor_roles', ['Supervisor', 'supervisor']);
        config()->set('tickets.csr_roles', ['Customer Service', 'support']);
        config()->set('tickets.assignment.strategy', 'round_robin');
        config()->set('tickets.assignment.day_shift_start_hour', 6);
        config()->set('tickets.assignment.day_shift_end_hour', 18);
        config()->set('tickets.assignment.distribution_day_department', 'COMD');
        config()->set('tickets.assignment.distribution_night_department', 'GUARD');
        config()->set('tickets.assignment.tsd_department', 'TSD');
        config()->set('tickets.escalation.second_tier_after_minutes', 30);
        Carbon::setTestNow(now()->setTime(9, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ticket_past_sla_without_action_is_auto_escalated(): void
    {
        $csr = $this->csr();
        $supervisor = $this->supervisor('COMD');
        $crew = $this->staff('COMD');
        $customer = $this->customer();
        $category = $this->distributionCategory();

        $ticketId = (int) $this->actingAs($csr, 'sanctum')
            ->postJson('/api/v1/admin/tickets', [
                'customer_id' => $customer->id,
                'category_id' => $category->id,
                'channel' => 'call',
                'description' => 'No power in feeder line.',
            ])
            ->json('id');

        $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/endorse", [
                'assigned_to' => $crew->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_ASSIGNED);

        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->assertNotNull($supervisor->id);

        $ticket->forceFill([
            'sla_due_at' => now()->subMinute(),
        ])->save();

        $this->artisan('tickets:check-sla')
            ->expectsOutput('Processed 1 SLA breach(es).')
            ->assertSuccessful();

        $ticket->refresh();

        $this->assertSame(Ticket::STATUS_ESCALATED, $ticket->status);
        $this->assertDatabaseHas('ticket_escalations', [
            'ticket_id' => $ticket->id,
            'escalated_to' => 'COMD',
        ]);
    }

    public function test_distribution_ticket_flagged_for_tsd_is_reassigned_and_sla_resets(): void
    {
        $csr = $this->csr();
        $this->supervisor('COMD');
        $this->supervisor('TSD');
        $comdCrew = $this->staff('COMD');
        $tsdCrew = $this->staff('TSD');
        $customer = $this->customer('tsd');
        $category = $this->distributionCategory();

        $ticketId = (int) $this->actingAs($csr, 'sanctum')
            ->postJson('/api/v1/admin/tickets', [
                'customer_id' => $customer->id,
                'category_id' => $category->id,
                'channel' => 'call',
                'description' => 'Distribution issue that may need TSD.',
            ])
            ->json('id');

        $endorse = $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/endorse", [
                'assigned_to' => $comdCrew->id,
            ]);

        $endorse->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_ASSIGNED)
            ->assertJsonPath('assigned_department', 'COMD');

        $originalDue = Ticket::query()->findOrFail($ticketId)->sla_due_at;
        Carbon::setTestNow(now()->addMinutes(5));

        $action = $this->actingAs($comdCrew, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/actions", [
                'action_taken' => 'Crew assessed issue and requests TSD help.',
                'minutes_taken' => 15,
                'requires_tsd_intervention' => true,
                'tsd_reason' => 'Sub-transmission support required.',
            ]);

        $action->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_ESCALATED)
            ->assertJsonPath('assigned_department', 'TSD')
            ->assertJsonPath('assigned_to', $tsdCrew->id);

        $ticket = Ticket::query()->findOrFail($ticketId);

        $this->assertNotNull($ticket->sla_due_at);
        $this->assertTrue($ticket->sla_due_at->gt($originalDue));
        $this->assertDatabaseHas('ticket_escalations', [
            'ticket_id' => $ticketId,
            'escalated_to' => 'TSD',
            'reason' => 'Sub-transmission support required.',
        ]);
    }

    private function distributionCategory(): TicketCategory
    {
        return TicketCategory::query()->where('name', 'Distribution Line')->firstOrFail();
    }

    private function csr(): User
    {
        return User::factory()->create([
            'role' => 'Customer Service',
            'department_code' => 'CSR',
        ]);
    }

    private function supervisor(string $departmentCode): User
    {
        return User::factory()->create([
            'role' => 'Supervisor',
            'department_code' => $departmentCode,
        ]);
    }

    private function staff(string $departmentCode): User
    {
        return User::factory()->create([
            'role' => 'staff',
            'department_code' => $departmentCode,
        ]);
    }

    private function customer(string $suffix = ''): User
    {
        $emailSuffix = $suffix === '' ? uniqid('', true) : $suffix.'-'.uniqid('', true);

        return User::factory()->create([
            'email' => 'ticket-automation-'.$emailSuffix.'@example.com',
            'role' => 'User',
        ]);
    }
}
