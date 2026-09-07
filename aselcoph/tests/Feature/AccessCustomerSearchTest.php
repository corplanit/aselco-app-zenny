<?php

namespace Tests\Feature;

use App\Http\Controllers\Access\AccessUserWebController;
use App\Models\AccountLink;
use App\Models\TAccountRaw;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccessCustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('t_accounts_raw')) {
            Schema::create('t_accounts_raw', function (Blueprint $table) {
                $table->string('account_no')->primary();
                $table->string('customer')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status')->nullable();
                $table->tinyInteger('isDeleted')->default(0);
                $table->string('meter_no')->nullable();
                $table->string('address')->nullable();
                $table->string('rate_class')->nullable();
            });
        }
    }

    public function test_customer_list_finds_members_by_name_and_account_prefix(): void
    {
        $admin = $this->staff();
        $match = User::factory()->create([
            'name' => 'Gemma Aba-a',
            'role' => 'User',
            'user_type' => 'customer',
        ]);
        $other = User::factory()->create([
            'name' => 'Other Member',
            'role' => 'User',
            'user_type' => 'customer',
        ]);
        AccountLink::query()->create([
            'user_id' => $match->id,
            'account_number' => '550006052',
            'owner_name' => 'ABA-A, GEMMA C.',
        ]);
        TAccountRaw::query()->create([
            'account_no' => '550006052',
            'customer' => 'ABA-A, GEMMA C.',
            'user_id' => $match->id,
            'status' => 'Linked',
            'isDeleted' => 0,
        ]);

        $byName = $this->customerList($admin, ['search' => 'Gemma']);
        $this->assertTrue($byName->contains(fn ($user) => $user->id === $match->id));
        $this->assertFalse($byName->contains(fn ($user) => $user->id === $other->id));

        $byAccount = $this->customerList($admin, ['search' => '55000']);
        $this->assertTrue($byAccount->contains(fn ($user) => $user->id === $match->id));
        $this->assertFalse($byAccount->contains(fn ($user) => $user->id === $other->id));
    }

    public function test_account_search_uses_prefix_and_ignores_short_queries(): void
    {
        $admin = $this->staff();
        TAccountRaw::query()->create([
            'account_no' => '550006052',
            'customer' => 'ABA-A, GEMMA C.',
            'user_id' => null,
            'status' => 'Active',
            'isDeleted' => 0,
        ]);
        TAccountRaw::query()->create([
            'account_no' => '880001111',
            'customer' => 'SANTOS, MARIA',
            'user_id' => null,
            'status' => 'Active',
            'isDeleted' => 0,
        ]);

        $this->actingAs($admin)
            ->getJson(route('access.users.accounts.search', ['q' => '5']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($admin)
            ->getJson(route('access.users.accounts.search', ['q' => '55000']))
            ->assertOk()
            ->assertJsonPath('data.0.account_no', '550006052')
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->getJson(route('access.users.accounts.search', ['q' => 'SAN']))
            ->assertOk()
            ->assertJsonPath('data.0.customer', 'SANTOS, MARIA');
    }

    private function staff(): User
    {
        return User::factory()->create([
            'email' => 'admin@aselco.ph',
            'role' => 'Administrator',
            'user_type' => 'support',
        ]);
    }

    private function customerList(User $admin, array $query)
    {
        $this->actingAs($admin);
        $request = Request::create(route('access.customers.index'), 'GET', $query);
        $request->setUserResolver(fn () => $admin);
        $view = app(AccessUserWebController::class)->customers($request);
        $this->assertSame('pages.staff.access.users-index', $view->name());

        return $view->getData()['users'];
    }
}
