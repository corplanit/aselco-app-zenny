<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tickets.admin_roles', ['Administrator', 'administrator']);
        config()->set('tickets.supervisor_roles', ['Supervisor', 'supervisor']);
        config()->set('tickets.csr_roles', ['Customer Service', 'support']);
        config()->set('tickets.attachment.max_kb', 10240);
        config()->set('tickets.attachment.allowed_mimes', ['jpg', 'jpeg', 'png', 'pdf', 'mp4']);
        config()->set('tickets.attachment.signed_url_ttl_minutes', 15);
        config()->set('tickets.attachment.disk', 'local');
        config()->set('tickets.attachment.directory', 'tickets');
        config()->set('tickets.assignment.strategy', 'round_robin');
        config()->set('tickets.assignment.day_shift_start_hour', 6);
        config()->set('tickets.assignment.day_shift_end_hour', 18);
        config()->set('tickets.assignment.distribution_day_department', 'COMD');
        config()->set('tickets.assignment.distribution_night_department', 'GUARD');
        config()->set('tickets.assignment.tsd_department', 'TSD');
        Carbon::setTestNow(now()->setTime(9, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ticket_lifecycle_closes_when_feedback_says_no_further_action(): void
    {
        $csr = $this->csr();
        $customer = $this->customer();
        $category = $this->distributionCategory();

        $create = $this->actingAs($csr, 'sanctum')->postJson('/api/v1/admin/tickets', [
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'subcategory' => 'Primary line issue',
            'channel' => 'call',
            'description' => 'Power interruption reported.',
            'priority' => 'high',
        ]);

        $create->assertCreated()
            ->assertJsonPath('status', Ticket::STATUS_NEW);

        $ticketId = (int) $create->json('id');
        $crew = $this->departmentStaff('COMD');

        $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/endorse", [
                'assigned_to' => $crew->id,
                'remarks' => 'Forward to COMD crew',
            ])
            ->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_ASSIGNED)
            ->assertJsonPath('assigned_department', 'COMD');

        $this->actingAs($crew, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/actions", [
                'action_taken' => 'Crew dispatched and restored feeder.',
                'minutes_taken' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_AWAITING_FEEDBACK);

        $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/feedback", [
                'method' => 'call',
                'customer_confirmed' => true,
                'notes' => 'Customer confirmed service restored.',
                'needs_further_action' => false,
            ])
            ->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_CLOSED);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'status' => Ticket::STATUS_CLOSED,
            'assigned_department' => 'COMD',
        ]);
        $this->assertSame(4, TicketStatusHistory::query()->where('ticket_id', $ticketId)->count());
    }

    public function test_ticket_lifecycle_loops_back_to_endorsed_when_feedback_needs_more_action(): void
    {
        $csr = $this->csr();
        $customer = $this->customer();
        $category = $this->distributionCategory();

        $ticketId = (int) $this->actingAs($csr, 'sanctum')
            ->postJson('/api/v1/admin/tickets', [
                'customer_id' => $customer->id,
                'category_id' => $category->id,
                'channel' => 'text',
                'description' => 'Voltage fluctuation reported.',
            ])
            ->json('id');

        $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/endorse")
            ->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_ASSIGNED);

        $crew = $this->departmentStaff('COMD');

        $this->actingAs($crew, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/actions", [
                'action_taken' => 'Initial patrol done, issue persists.',
                'minutes_taken' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_AWAITING_FEEDBACK);

        $feedback = $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/feedback", [
                'method' => 'message',
                'customer_confirmed' => false,
                'notes' => 'Customer says issue remains.',
                'needs_further_action' => true,
            ]);

        $feedback->assertOk()
            ->assertJsonPath('status', Ticket::STATUS_ASSIGNED);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'status' => Ticket::STATUS_ASSIGNED,
        ]);
        $this->assertSame(Ticket::STATUS_ASSIGNED, Ticket::query()->findOrFail($ticketId)->status);
    }

    public function test_customer_can_create_view_and_attach_only_to_own_tickets(): void
    {
        Storage::fake('local');

        $customer = $this->customer();
        $otherCustomer = $this->customer('other');
        $category = TicketCategory::query()->where('name', 'Billing & Collection')->firstOrFail();

        $create = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/tickets', [
                'category_id' => $category->id,
                'description' => 'Please check my billing concern.',
                'priority' => 'normal',
            ]);

        $create->assertCreated()
            ->assertJsonPath('created_by', 'customer')
            ->assertJsonPath('channel', 'app');

        $ticketId = (int) $create->json('id');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/customer/tickets')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $file = UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf');

        $attach = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/customer/tickets/{$ticketId}/attachments", [
                'attachment' => $file,
            ]);

        $attach->assertCreated()
            ->assertJsonPath('data.file_type', 'application/pdf');

        $attachmentId = TicketAttachment::query()->value('id');

        $downloadUrl = $attach->json('data.download_url');
        $this->actingAs($customer, 'sanctum')
            ->get($downloadUrl)
            ->assertOk();

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson("/api/v1/customer/tickets/{$ticketId}")
            ->assertNotFound();

        $this->actingAs($otherCustomer, 'sanctum')
            ->get($downloadUrl)
            ->assertForbidden();

        $this->assertNotNull($attachmentId);
        Storage::disk('local')->assertExists(TicketAttachment::query()->firstOrFail()->file_path);
    }

    public function test_support_cannot_open_ticket_unless_it_is_assigned_to_them(): void
    {
        $csr = $this->csr();
        $customer = $this->customer();
        $category = $this->distributionCategory();
        $assignee = $this->departmentStaff('COMD');
        $otherComd = $this->departmentStaff('COMD');
        $ccadStaff = $this->departmentStaff('CCAD');

        $ticketId = (int) $this->actingAs($csr, 'sanctum')
            ->postJson('/api/v1/admin/tickets', [
                'customer_id' => $customer->id,
                'category_id' => $category->id,
                'channel' => 'social/sms',
                'description' => 'Line fault from social report.',
            ])
            ->json('id');

        $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/endorse", [
                'assigned_to' => $assignee->id,
            ])
            ->assertOk()
            ->assertJsonPath('assigned_to', $assignee->id);

        $this->actingAs($assignee, 'sanctum')
            ->getJson("/api/v1/admin/tickets/{$ticketId}")
            ->assertOk();

        $this->actingAs($otherComd, 'sanctum')
            ->getJson("/api/v1/admin/tickets/{$ticketId}")
            ->assertForbidden();

        $this->actingAs($ccadStaff, 'sanctum')
            ->getJson("/api/v1/admin/tickets/{$ticketId}")
            ->assertForbidden();
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

    private function departmentStaff(string $departmentCode): User
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
            'name' => 'Ticket Customer '.strtoupper($suffix),
            'email' => 'ticket-'.$emailSuffix.'@example.com',
            'role' => 'User',
        ]);
    }
}
