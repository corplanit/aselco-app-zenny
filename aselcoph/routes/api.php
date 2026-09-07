<?php

use App\Http\Controllers\Api\Admin\AdminAiController;
use App\Http\Controllers\Api\Admin\AdminKnowledgeController;
use App\Http\Controllers\Api\Admin\AdminTicketAiController;
use App\Http\Controllers\Api\Admin\AdminWalletController;
use App\Http\Controllers\Api\Admin\AdminTicketController;
use App\Http\Controllers\Api\Admin\AccessAdminApiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerAiController;
use App\Http\Controllers\Api\V1\SupportChatApiController;
use App\Http\Controllers\Api\V1\CustomerTicketController;
use App\Http\Controllers\Api\V1\CustomerWalletController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\TicketAttachmentDownloadController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| Legacy API routes
|--------------------------------------------------------------------------
|
| Deprecated in favor of /api/v1/auth/user. Kept for backward compatibility.
|
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Mobile API v1 — token-based auth (Sanctum personal access tokens)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/openapi.json', function () {
        $path = base_path('docs/api/openapi-v1.yaml');
        $spec = Yaml::parseFile($path);

        return response()->json($spec);
    })->withoutMiddleware('throttle:api');

    Route::get('/meta', function () {
        return response()->json([
            'name' => 'ASELCO Member API',
            'version' => 'v1',
            'success_convention' => 'Resource object or Laravel paginator. Optional message/code on mutations.',
            'error_convention' => ['message' => 'string', 'code' => 'string', 'errors' => 'object (validation only)'],
            'auth' => 'Bearer Sanctum personal access token. Admin wallet also requires can.load-wallet; admin tickets require can.manage-tickets.',
        ]);
    });
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:api-register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:api-login');

        // Public resend: login does not issue tokens to unverified users.
        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);

            Route::get('/user', [AuthController::class, 'user'])
                ->middleware('verified');
        });
    });

    Route::prefix('membership')->middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/status', [MembershipController::class, 'status']);
        Route::get('/privacy', [MembershipController::class, 'privacy']);
        Route::get('/account-links', [MembershipController::class, 'index']);
        Route::post('/account-links', [MembershipController::class, 'store'])
            ->middleware('throttle:api-membership');
        Route::get('/profile', [MembershipController::class, 'showProfile']);
        Route::put('/profile', [MembershipController::class, 'upsertProfile'])
            ->middleware('throttle:api-membership');
        Route::get('/linked-accounts', [MembershipController::class, 'linkedAccounts']);
    });

    Route::prefix('dashboard')->middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
    });

    Route::prefix('ledger')->middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/', [LedgerController::class, 'show'])
            ->middleware('throttle:api-ledger');
    });

    Route::prefix('wallet')->middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/', [WalletController::class, 'show']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::post('/pay', [WalletController::class, 'pay'])
            ->middleware('throttle:api-wallet');
    });

    Route::prefix('admin/wallet')->middleware(['auth:sanctum', 'can.load-wallet'])->group(function () {
        Route::get('/summary', [AdminWalletController::class, 'summary']);
        Route::get('/load-requests', [AdminWalletController::class, 'loadRequests']);
        Route::post('/load-requests/{id}/approve', [AdminWalletController::class, 'approve'])
            ->middleware('throttle:admin-wallet-load')
            ->whereNumber('id');
        Route::post('/load-requests/{id}/reject', [AdminWalletController::class, 'reject'])
            ->middleware('throttle:admin-wallet-load')
            ->whereNumber('id');
        Route::post('/load', [AdminWalletController::class, 'load'])
            ->middleware('throttle:admin-wallet-load');
        Route::get('/{customer_id}/transactions', [AdminWalletController::class, 'transactions'])
            ->whereNumber('customer_id');
    });

    Route::prefix('admin/tickets')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api-tickets'])->group(function () {
        Route::post('/', [AdminTicketController::class, 'store'])
            ->middleware('throttle:api-ticket-create');
        Route::get('/', [AdminTicketController::class, 'index']);
        Route::get('/ai/dashboard', [AdminTicketAiController::class, 'dashboard']);
        Route::get('/{id}', [AdminTicketController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/ai', [AdminTicketAiController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/ai/analyze', [AdminTicketAiController::class, 'analyze'])->whereNumber('id');
        Route::post('/{id}/ai/apply-priority', [AdminTicketAiController::class, 'applyPriority'])->whereNumber('id');
        Route::post('/{id}/ai/suggest-response', [AdminTicketAiController::class, 'suggestResponse'])->whereNumber('id');
        Route::post('/{id}/ai/drafts/{draftId}/review', [AdminTicketAiController::class, 'reviewDraft'])
            ->whereNumber('id')
            ->whereNumber('draftId');
        Route::post('/{id}/endorse', [AdminTicketController::class, 'endorse'])->whereNumber('id');
        Route::post('/{id}/actions', [AdminTicketController::class, 'actions'])->whereNumber('id');
        Route::post('/{id}/feedback', [AdminTicketController::class, 'feedback'])->whereNumber('id');
        Route::post('/{id}/escalate', [AdminTicketController::class, 'escalate'])->whereNumber('id');
        Route::post('/{id}/status', [AdminTicketController::class, 'status'])->whereNumber('id');
        Route::post('/{id}/close', [AdminTicketController::class, 'close'])->whereNumber('id');
        Route::post('/{id}/attachments', [AdminTicketController::class, 'attachments'])->whereNumber('id');
    });

    Route::prefix('admin/notifications')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api-notifications'])->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
    });

    Route::prefix('customer/support-chat')->middleware(['auth:sanctum', 'verified', 'throttle:api'])->group(function () {
        Route::post('/ensure', [SupportChatApiController::class, 'ensure']);
        Route::get('/', [SupportChatApiController::class, 'show']);
        Route::get('/{id}/messages', [SupportChatApiController::class, 'messages'])->whereNumber('id');
        Route::post('/{id}/messages', [SupportChatApiController::class, 'send'])->whereNumber('id');
        Route::post('/{id}/read', [SupportChatApiController::class, 'read'])->whereNumber('id');
    });

    Route::prefix('support/chat')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api'])->group(function () {
        Route::get('/', [SupportChatApiController::class, 'inbox']);
        Route::get('/{id}/messages', [SupportChatApiController::class, 'messages'])->whereNumber('id');
        Route::post('/{id}/messages', [SupportChatApiController::class, 'send'])->whereNumber('id');
        Route::post('/{id}/read', [SupportChatApiController::class, 'read'])->whereNumber('id');
    });

    Route::prefix('customer/ai')->middleware(['auth:sanctum', 'verified', 'throttle:api-ai'])->group(function () {
        Route::get('/bootstrap', [CustomerAiController::class, 'bootstrap']);
        Route::post('/chat', [CustomerAiController::class, 'chat']);
        Route::post('/inquiry', [CustomerAiController::class, 'inquiry']);
        Route::post('/search', [CustomerAiController::class, 'search']);
        Route::post('/escalate', [CustomerAiController::class, 'escalate']);
        Route::get('/conversations', [CustomerAiController::class, 'conversations']);
        Route::get('/conversations/{id}', [CustomerAiController::class, 'show'])->whereNumber('id');
        Route::delete('/conversations/{id}', [CustomerAiController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('admin/ai')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api-ai'])->group(function () {
        Route::post('/assist', [AdminAiController::class, 'assist']);
    });

    Route::prefix('admin/knowledge')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api-ai'])->group(function () {
        Route::get('/', [AdminKnowledgeController::class, 'index']);
        Route::post('/', [AdminKnowledgeController::class, 'store']);
        Route::get('/categories', [AdminKnowledgeController::class, 'categories']);
        Route::post('/search', [AdminKnowledgeController::class, 'search']);
        Route::get('/{id}', [AdminKnowledgeController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [AdminKnowledgeController::class, 'update'])->whereNumber('id');
        Route::post('/{id}', [AdminKnowledgeController::class, 'update'])->whereNumber('id'); // multipart-friendly
        Route::delete('/{id}', [AdminKnowledgeController::class, 'destroy'])->whereNumber('id');
        Route::post('/{id}/index', [AdminKnowledgeController::class, 'indexDocument'])->whereNumber('id');
    });

    Route::prefix('admin')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api-tickets'])->group(function () {
        Route::get('/users', [AccessAdminApiController::class, 'users']);
        Route::post('/users', [AccessAdminApiController::class, 'storeUser']);
        Route::get('/users/{user}', [AccessAdminApiController::class, 'showUser'])->whereNumber('user');
        Route::put('/users/{user}', [AccessAdminApiController::class, 'updateUser'])->whereNumber('user');
        Route::delete('/users/{user}', [AccessAdminApiController::class, 'destroyUser'])->whereNumber('user');
        Route::put('/users/{user}/permissions', [AccessAdminApiController::class, 'updateUserPermissions'])->whereNumber('user');
        Route::get('/departments', [AccessAdminApiController::class, 'departments']);
        Route::post('/departments', [AccessAdminApiController::class, 'storeDepartment']);
        Route::get('/departments/{department}', [AccessAdminApiController::class, 'showDepartment']);
        Route::put('/departments/{department}', [AccessAdminApiController::class, 'updateDepartment']);
        Route::get('/roles', [AccessAdminApiController::class, 'roles']);
        Route::post('/roles', [AccessAdminApiController::class, 'storeRole']);
        Route::put('/roles/{role}', [AccessAdminApiController::class, 'updateRole']);
        Route::get('/permissions', [AccessAdminApiController::class, 'permissions']);
        Route::get('/roles/{role}/permissions', [AccessAdminApiController::class, 'rolePermissions']);
        Route::put('/roles/{role}/permissions', [AccessAdminApiController::class, 'updateRolePermissions']);
        Route::get('/tickets/{id}/assignment', [AccessAdminApiController::class, 'assignment'])->whereNumber('id');
        Route::post('/tickets/{id}/assign', [AccessAdminApiController::class, 'assign'])->whereNumber('id');
        Route::post('/tickets/{id}/reassign', [AccessAdminApiController::class, 'reassign'])->whereNumber('id');
        Route::get('/tickets/{id}/assignment-history', [AccessAdminApiController::class, 'assignmentHistory'])->whereNumber('id');
    });

    Route::prefix('support')->middleware(['auth:sanctum', 'can.manage-tickets', 'throttle:api-tickets'])->group(function () {
        Route::get('/tickets', [AccessAdminApiController::class, 'supportTickets']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/dashboard', [AccessAdminApiController::class, 'supportDashboard']);
        Route::get('/profile', [AccessAdminApiController::class, 'supportProfile']);
    });

    Route::prefix('customer/tickets')->middleware(['auth:sanctum', 'verified', 'throttle:api-tickets'])->group(function () {
        Route::post('/', [CustomerTicketController::class, 'store'])
            ->middleware('throttle:api-ticket-create');
        Route::get('/', [CustomerTicketController::class, 'index']);
        Route::get('/{id}', [CustomerTicketController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/attachments', [CustomerTicketController::class, 'attachments'])->whereNumber('id');
        Route::post('/{id}/feedback', [CustomerTicketController::class, 'feedback'])->whereNumber('id');
    });

    Route::prefix('customer/wallet')->middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/balance', [CustomerWalletController::class, 'balance']);
        Route::get('/transactions', [CustomerWalletController::class, 'transactions']);
        Route::post('/pay-bill', [CustomerWalletController::class, 'payBill'])
            ->middleware('throttle:api-wallet');
    });

    Route::get('/tickets/attachments/{attachment}', TicketAttachmentDownloadController::class)
        ->middleware(['auth:sanctum', 'signed'])
        ->whereNumber('attachment')
        ->name('api.v1.tickets.attachments.download');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/devices', [DeviceTokenController::class, 'store'])
            ->middleware('throttle:api-notifications');
        Route::delete('/devices', [DeviceTokenController::class, 'destroy'])
            ->middleware('throttle:api-notifications');
        // Back-compat alias used by older mobile builds
        Route::post('/device-tokens', [DeviceTokenController::class, 'store'])
            ->middleware('throttle:api-notifications');
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])
            ->middleware('throttle:api-notifications');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->middleware('throttle:api-notifications');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
            ->middleware('throttle:api-notifications')
            ->whereNumber('id');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->middleware('throttle:api-notifications');

        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
    });
});
