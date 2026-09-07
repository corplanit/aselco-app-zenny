<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Chats\Conversation;
use App\Models\Chats\ConversationParticipant;
use App\Models\User;
use App\Services\SupportChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class SupportChatUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('chat_conversation')) {
            Schema::create('chat_conversation', function (Blueprint $table) {
                $table->id();
                $table->string('type')->default('group');
                $table->string('kind', 32)->default('general')->index();
                $table->foreignId('customer_id')->nullable();
                $table->string('status', 32)->default('open')->index();
                $table->string('name')->nullable();
                $table->string('photo')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->boolean('is_public')->default(false);
                $table->json('settings')->nullable();
                $table->boolean('is_group')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('chat_conversation', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_conversation', 'kind')) {
                    $table->string('kind', 32)->default('general')->index();
                }
                if (! Schema::hasColumn('chat_conversation', 'customer_id')) {
                    $table->unsignedBigInteger('customer_id')->nullable()->index();
                }
                if (! Schema::hasColumn('chat_conversation', 'status')) {
                    $table->string('status', 32)->default('open')->index();
                }
            });
        }

        if (! Schema::hasTable('chat_conversation_participants')) {
            Schema::create('chat_conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id');
                $table->foreignId('user_id');
                $table->string('role')->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->boolean('is_trashed')->default(false);
                $table->boolean('is_muted')->default(false);
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id');
                $table->foreignId('user_id');
                $table->string('type')->nullable();
                $table->text('body')->nullable();
                $table->unsignedBigInteger('reply_to_message_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('edited_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function test_ensure_creates_one_open_support_room_per_customer(): void
    {
        $customer = User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
            'email_verified_at' => now(),
        ]);
        User::factory()->create([
            'role' => 'support',
            'user_type' => 'support',
            'email_verified_at' => now(),
        ]);

        $service = app(SupportChatService::class);
        $first = $service->ensureForCustomer($customer);
        $second = $service->ensureForCustomer($customer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(Conversation::KIND_SUPPORT, $first->kind);
        $this->assertSame($customer->id, $first->customer_id);
        $this->assertTrue(
            ConversationParticipant::query()
                ->where('conversation_id', $first->id)
                ->where('user_id', $customer->id)
                ->exists()
        );
    }

    public function test_customer_api_can_send_message(): void
    {
        Event::fake([MessageSent::class]);

        $customer = User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
            'email_verified_at' => now(),
        ]);
        $agent = User::factory()->create([
            'role' => 'support',
            'user_type' => 'support',
            'email_verified_at' => now(),
        ]);

        $conversation = app(SupportChatService::class)->ensureForCustomer($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/customer/support-chat/{$conversation->id}/messages", [
                'body' => 'Hello support',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.body', 'Hello support');
        Event::assertDispatched(MessageSent::class);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $agent->id,
            'category' => 'chat',
            'title' => $customer->name,
        ]);
    }

    public function test_other_customer_cannot_access_room(): void
    {
        $customer = User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
            'email_verified_at' => now(),
        ]);
        $other = User::factory()->create([
            'role' => 'User',
            'user_type' => 'customer',
            'email_verified_at' => now(),
        ]);
        User::factory()->create([
            'role' => 'support',
            'user_type' => 'support',
            'email_verified_at' => now(),
        ]);

        $conversation = app(SupportChatService::class)->ensureForCustomer($customer);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/customer/support-chat/{$conversation->id}/messages")
            ->assertForbidden();
    }
}
