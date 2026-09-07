<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerController;
use App\Models\TAccountRaw;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumerListTest extends TestCase
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

    public function test_consumer_list_search_and_status_filter(): void
    {
        $linkedUser = User::factory()->create(['contact_no' => '09171234567']);

        TAccountRaw::query()->create([
            'account_no' => '0102-111111',
            'customer' => 'Juan Dela Cruz',
            'user_id' => $linkedUser->id,
            'status' => 'Linked',
            'isDeleted' => 0,
        ]);
        TAccountRaw::query()->create([
            'account_no' => '0102-222222',
            'customer' => 'Maria Santos',
            'user_id' => null,
            'status' => 'Inactive',
            'isDeleted' => 0,
        ]);

        $searched = $this->list(['search' => 'Juan']);
        $this->assertTrue($searched->contains(fn ($row) => $row->account_no === '0102-111111'));
        $this->assertFalse($searched->contains(fn ($row) => $row->account_no === '0102-222222'));
        $this->assertSame($linkedUser->email, $searched->firstWhere('account_no', '0102-111111')->email);

        $inactive = $this->list(['status' => 'Inactive']);
        $this->assertTrue($inactive->contains(fn ($row) => $row->account_no === '0102-222222'));
        $this->assertFalse($inactive->contains(fn ($row) => $row->account_no === '0102-111111'));

        $unlinked = $this->list(['portal' => 'unlinked']);
        $this->assertTrue($unlinked->contains(fn ($row) => $row->account_no === '0102-222222'));
        $this->assertFalse($unlinked->contains(fn ($row) => $row->account_no === '0102-111111'));
    }

    private function list(array $query)
    {
        $request = Request::create(route('consumer.list'), 'GET', $query);
        $view = app(CustomerController::class)->index($request);

        $this->assertSame('pages.customer.index', $view->name());

        return $view->getData()['consumers'];
    }
}
