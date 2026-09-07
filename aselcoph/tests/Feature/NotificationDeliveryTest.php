<?php

namespace Tests\Feature;

use App\Mail\SystemNotificationMail;
use App\Mail\WalletLoadedMail;
use App\Models\AccountLink;
use App\Models\AppNotification;
use App\Models\BillingUpload;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\AstWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
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
        config()->set('ast.load_wallet_roles', [
            'Administrator',
            'administrator',
            'Customer Service',
            'staff',
            'support',
        ]);
        Carbon::setTestNow(now()->setTime(9, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ticket_assignment_delivers_customer_and_staff_notifications_and_admin_api_marks_read(): void
    {
        Mail::fake();

        $csr = $this->staff('Customer Service', 'CSR');
        $crew = $this->staff('staff', 'COMD');
        $customer = $this->customer();
        $category = TicketCategory::query()->where('name', 'Distribution Line')->firstOrFail();

        $ticketId = (int) $this->actingAs($csr, 'sanctum')
            ->postJson('/api/v1/admin/tickets', [
                'customer_id' => $customer->id,
                'category_id' => $category->id,
                'channel' => 'call',
                'description' => 'Transformer issue reported.',
            ])
            ->json('id');

        $this->actingAs($csr, 'sanctum')
            ->postJson("/api/v1/admin/tickets/{$ticketId}/endorse", [
                'assigned_to' => $crew->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'title' => __('notifications.ticket.submitted_title'),
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'title' => __('notifications.ticket.assigned_title'),
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $crew->id,
            'title' => __('notifications.ticket.staff_assigned_title'),
        ]);

        Mail::assertSent(SystemNotificationMail::class, fn (SystemNotificationMail $mail) => $mail->hasTo($crew->email));

        $adminList = $this->actingAs($crew, 'sanctum')
            ->getJson('/api/v1/admin/notifications');

        $adminList->assertOk()
            ->assertJsonPath('data.0.title', __('notifications.ticket.staff_assigned_title'));

        $notificationId = AppNotification::query()->where('user_id', $crew->id)->latest('id')->value('id');

        $this->actingAs($crew, 'sanctum')
            ->postJson("/api/v1/admin/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('notification.unread', false);
    }

    public function test_wallet_payment_failure_and_load_notifications_are_delivered(): void
    {
        Mail::fake();

        $customer = $this->customer();
        $staff = $this->staff('Administrator', null);
        $approver = $this->staff('Customer Service', null);
        $link = $this->linkAccount($customer, '0102-999001');
        $bill = $this->billing($link, $staff, '50.00');

        app(AstWalletService::class)->load($staff, '0102-999001', '10.00', 'notify-seed');

        $this->actingAs($staff, 'sanctum')
            ->withHeader('Idempotency-Key', 'phase2-load-notify')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '100.00',
                'reference_no' => 'LOAD-NOTIFY-SUCCESS',
            ])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'notify-fail')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '20.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AST_INSUFFICIENT_FUNDS');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'title' => __('notifications.wallet.payment_failed_title'),
        ]);

        $this->actingAs($staff, 'sanctum')
            ->withHeader('Idempotency-Key', 'approval-notify')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '10000.00',
                'reference_no' => 'LOAD-NOTIFY-1',
            ])
            ->assertStatus(202);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $approver->id,
            'title' => __('notifications.wallet.load_approval_title'),
        ]);

        Mail::assertSent(WalletLoadedMail::class, 1);
        Mail::assertSent(SystemNotificationMail::class);
    }

    private function customer(string $suffix = ''): User
    {
        $emailSuffix = $suffix === '' ? uniqid('', true) : $suffix.'-'.uniqid('', true);

        return User::factory()->create([
            'email' => 'notifications-'.$emailSuffix.'@example.com',
            'role' => 'User',
            'contact_no' => '09171234567',
        ]);
    }

    private function staff(string $role, ?string $departmentCode): User
    {
        return User::factory()->create([
            'role' => $role,
            'department_code' => $departmentCode,
        ]);
    }

    private function linkAccount(User $user, string $accountNumber): AccountLink
    {
        return AccountLink::query()->create([
            'user_id' => $user->id,
            'account_number' => $accountNumber,
            'owner_name' => $user->name,
            'validated_at' => now(),
            'validated_by' => 'test',
        ]);
    }

    private function billing(AccountLink $link, User $staff, string $amount): BillingUpload
    {
        return BillingUpload::query()->create([
            'account_link_id' => $link->id,
            'file_path' => 'billing_pdfs/test.pdf',
            'amount' => $amount,
            'billing_date' => now()->toDateString(),
            'uploaded_by' => $staff->id,
            'status' => BillingUpload::STATUS_PENDING,
            'paid_amount' => '0.00',
            'balance_due' => $amount,
        ]);
    }
}
