<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_users_and_tickets(): void
    {
        $this->seed(AccessSeeder::class);

        $admin = User::factory()->create([
            'email' => 'admin@aselco.ph',
            'role' => 'Administrator',
        ]);

        $access = app(AccessService::class);
        $this->assertTrue($access->isSuperAdmin($admin));
        $this->assertTrue($access->allows($admin, 'users.view'));
        $this->assertTrue($access->allows($admin, 'tickets.assign'));
    }

    public function test_customer_cannot_manage_users(): void
    {
        $customer = User::factory()->create([
            'role' => 'User',
        ]);

        $access = app(AccessService::class);
        $this->assertFalse($access->allows($customer, 'users.view'));
        $this->assertTrue($access->allows($customer, 'dashboard.view'));
    }
}
