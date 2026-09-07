<?php

use App\Http\Controllers\AccountLinkController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BillingApiController;
use App\Http\Controllers\BillingUploadController;
use App\Http\Controllers\Chats\ConversationController;
use App\Http\Controllers\Chats\MessagesController;
use App\Http\Controllers\Chats\SupportChatController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AstWalletAdminController;
use App\Http\Controllers\AstAdminWebController;
use App\Http\Controllers\TicketAdminWebController;
use App\Http\Controllers\KnowledgeAdminWebController;
use App\Http\Controllers\CustomerComplaintController;
use App\Http\Controllers\FileManagerController;
use App\Models\TAccountRaw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\blogController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\ChunkUploadController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\postController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Access\AccessUserWebController;
use App\Http\Controllers\Access\AccessOrgWebController;
use App\Http\Controllers\Access\SupportWorkspaceWebController;
use App\Models\postModel;
use App\Models\User;

use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\pagesController;
use App\Models\FileManager;

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/api/docs', function () {
    return view('api.docs');
});

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

Route::get('/blog/{id}/{slug}', function ($id, $slug) {
    return view('home.blog.index', compact('id', 'slug'));
});

Route::get('/storage/{link}', function ($link) {
    return view('home.components.storage', compact('link'));
});

Route::middleware(['auth'])->get('/supp/chat/unread/total', [\App\Http\Controllers\Chats\SupportChatController::class, 'unreadTotal']);
Route::middleware(['auth'])->get('/chats/support/unread-total', [\App\Http\Controllers\Chats\SupportChatController::class, 'unreadTotal'])
    ->name('chats.support.unread');

Route::middleware(['auth'])->prefix('supp/chat')->group(function () {
    Route::any('{any?}', function () {
        return redirect()->route('chats.support');
    })->where('any', '.*');
});


// Legacy Supp* routes redirected above.
// Route::get('/login', function () {
//     if (Auth::check()) {
//         return redirect('/dashboard');
//     }

//     return redirect('/');
// })->name('login');

Route::prefix('/page')->group(function () {
    Route::get('/about-us', function () {
        return view('home.pages.about-us');
    });

    Route::get('/history', function () {
        return view('home.pages.history');
    });
});

Route::get('/temp/logout', function (Request $request) {
    Auth::logout(); // Log out the user

    $request->session()->invalidate(); // Invalidate the session
    $request->session()->regenerateToken(); // Regenerate CSRF token

    return redirect('/')->with('success', 'You have been logged out.');
});

// Show the verification notice
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})
    ->middleware(['auth'])
    ->name('verification.notice');

// Handle the email verification link click
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/dashboard'); // or wherever you want after verification
})
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

// Resend the verification email
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('status', 'verification-link-sent');
})
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::middleware(['auth:sanctum', config('jetstream.auth_session', 'verified')])->group(function () {
    Route::get('/dashboard', function () {
        return redirect(Auth::user()->loginHomePath());
    });

    Route::middleware('auth')->group(function () {
        Route::get('/chats', [SupportChatController::class, 'index'])->name('chats.support');
        Route::get('/chats/support', [SupportChatController::class, 'index']);
        Route::post('/chats/support/ensure', [SupportChatController::class, 'ensure'])->name('chats.support.ensure');
        Route::get('/chats/support/inbox', [SupportChatController::class, 'inbox'])->name('chats.support.inbox');
        Route::get('/chats/support/{conversation}/messages', [SupportChatController::class, 'messages'])
            ->whereNumber('conversation')
            ->name('chats.support.messages');
        Route::post('/chats/support/{conversation}/messages', [SupportChatController::class, 'send'])
            ->whereNumber('conversation')
            ->name('chats.support.send');
        Route::post('/chats/support/{conversation}/read', [SupportChatController::class, 'read'])
            ->whereNumber('conversation')
            ->name('chats.support.read');

        Route::get('/chats/monitor', [ConversationController::class, 'monitor'])->name('chats.monitor');
        Route::get('/chats/monitor/{id}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::get('/chats/legacy', [ConversationController::class, 'index'])->name('chats.show');
    });
    Route::middleware(['auth'])->group(function () {
        // Create new DM / Group (used by the "New Chat" modal)
        Route::post('/chats/conversations', [ConversationController::class, 'store'])->name('chats.conversations.store');

        // Rename / delete conversation (group)
        Route::put('/chats/{conversation}', [ConversationController::class, 'update'])->name('chats.conversations.update');
        Route::delete('/chats/{conversation}', [ConversationController::class, 'destroy'])->name('chats.conversations.destroy');

        // Members management (group only)
        Route::post('/chats/{conversation}/members', [ConversationController::class, 'addMember'])->name('chats.conversations.members.add');
        Route::delete('/chats/{conversation}/members/{user}', [ConversationController::class, 'removeMember'])->name('chats.conversations.members.remove');

        // Mark as read (used by JS: axios.post(`/chats/${convId}/read`))
        Route::post('/chats/{conversation}/read', [ConversationController::class, 'markAsRead'])->name('chats.read');

        // Messages API
        Route::get('/chats/{conversation}/messages', [MessagesController::class, 'index'])->name('chats.messages.index');

        Route::post('/chats/{conversation}/messages', [MessagesController::class, 'store'])->name('chats.messages.store');

        Route::post('/chats/{conversation}/messages', [MessagesController::class, 'store'])->name('chats.messages.store');
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('/calendar', [CalendarEventController::class, 'view'])->name('calendar.view');
        Route::get('/calendar-events', [CalendarEventController::class, 'index']);
        Route::post('/calendar-events', [CalendarEventController::class, 'store']);
        Route::put('/calendar-events/{event}', [CalendarEventController::class, 'update']);
        Route::delete('/calendar-events/{event}', [CalendarEventController::class, 'destroy']);
    });

    Route::get('/t/dashboard', [BillingUploadController::class, 'dashboard']);
    Route::get('/u/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::prefix('/admin')->group(function () {
        route::get('/news-and-events', function () {
            return view('pages.admin.cms.news-and-events');
        });

        Route::get('/new-blog', [blogController::class, 'create']);
    });

    Route::prefix('/customer')->group(function () {
        Route::get('/registration', [CustomerController::class, 'create']);
        Route::post('/store', [AccountLinkController::class, 'store'])->name('customer.store');
        Route::post('/complaints/update-status', [CustomerComplaintController::class, 'updateStatus'])->name('complaints.update');
        Route::post('/api/bill', [BillingUploadController::class, 'list'])->name('api.bill');

        Route::post('/api/complaints', [CustomerComplaintController::class, 'list'])->name('api.complaints');
        Route::post('/api/complaints/all', [CustomerComplaintController::class, 'list_all'])->name('api.complaints.admin');
    });

    Route::prefix('/consumer')->group(function () {
        Route::get('/list', [CustomerController::class, 'index'])->name('consumer.list');
        Route::post('/store', [AccountLinkController::class, 'store'])->name('link.store');
        Route::post('/update', [AccountLinkController::class, 'update'])->name('link.update');

        Route::post('/api/list', [CustomerController::class, 'list'])->name('api.consumer');
        Route::post('/account/link/create', [CustomerController::class, 'createAccountForRaw'])->name('account.link.create');
    });

    Route::post('/delete', function (Request $request) {
        $id = $request->id;
        $type = $request->type;

        if ($type == 'consumer') {
            TAccountRaw::query()
                ->where(function ($query) use ($id) {
                    $query->where('account_no', $id);
                    if (is_numeric($id) && \Illuminate\Support\Facades\Schema::hasColumn('t_accounts_raw', 'id')) {
                        $query->orWhere('id', $id);
                    }
                })
                ->update(['isDeleted' => 1]);
        } elseif ($type == 'post') {
            postModel::where('post_id', $id)->update(['isDeleted' => 1]);
        }
    })
        ->name('trash.bin')
        ->middleware('auth');

    Route::post('/user/search', function (Request $request) {
        $user = User::where('email', $request->email)->first();
        return response()->json(['user' => $user]);
    })->name('user.search');

    Route::get('/accounts/user/{user_id}', function ($user_id) {
        $accounts = TAccountRaw::where('user_id', $user_id)->get();
        return response()->json($accounts);
    });
    Route::post('/account/link/existing', [CustomerController::class, 'linkToExistingUser'])->name('account.link.existing');
    Route::post('/account/update', [CustomerController::class, 'updateAccount'])->name('account.update');
    Route::post('/account/password/change', [CustomerController::class, 'changePassword'])->name('account.password.change');
    Route::post('/account/password/reset', [CustomerController::class, 'resetPassword'])->name('account.password.reset');
    Route::post('/account/register', [CustomerController::class, 'store'])->name('account.register');

    Route::get('/validation', [BillingUploadController::class, 'validation']);
    Route::get('/api/account/validation', [BillingUploadController::class, 'accounts'])->name('api.account.validation');
    Route::get('/api/account/validation/all', [BillingUploadController::class, 'accounts_all'])->name('api.account.validation.all');

    Route::get('/billing-upload', [BillingUploadController::class, 'create'])->name('billing.upload.create');
    Route::post('/billing-upload', [BillingUploadController::class, 'store'])->name('billing.upload.store');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::get('/announcements/search-users', [AnnouncementController::class, 'searchUsers'])->name('announcements.search-users');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::post('/announcements/preview-audience', [AnnouncementController::class, 'previewAudience'])->name('announcements.preview');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('announcements.publish');

    Route::prefix('/link')->group(function () {
        Route::post('/store', [AccountLinkController::class, 'store'])->name('link.store');
        Route::post('/update', [AccountLinkController::class, 'update'])->name('link.update');
    });

    Route::post('/survey/update-link', [BillingUploadController::class, 'updateAccountNumber']);


    // Route::get('/chats', function () {
    //     return view('chats.index');
    // });

    Route::get('/support', function () {
        return redirect()->route('chats.support');
    });

    Route::get('/complaint', function () {
        return view('pages.staff.complaint');
    });

    Route::middleware('can.manage-tickets')->prefix('/tickets')->name('tickets.')->group(function () {
        Route::get('/', [TicketAdminWebController::class, 'queue'])->name('queue');
        Route::get('/intake', [TicketAdminWebController::class, 'intake'])->name('intake');
        Route::post('/', [TicketAdminWebController::class, 'store'])->name('store');
        Route::get('/customer-search', [TicketAdminWebController::class, 'customerSearch'])->name('customer-search');
        Route::post('/customers', [TicketAdminWebController::class, 'createCustomer'])->name('customers.store');
        Route::get('/escalations', [TicketAdminWebController::class, 'escalations'])->name('escalations');
        Route::get('/reports', [TicketAdminWebController::class, 'reports'])->name('reports');
        Route::get('/ai', [TicketAdminWebController::class, 'aiDashboard'])->name('ai');
        Route::get('/notifications', [TicketAdminWebController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [TicketAdminWebController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [TicketAdminWebController::class, 'markNotificationRead'])->name('notifications.read')->whereNumber('id');
        Route::get('/attachments/{attachment}', [TicketAdminWebController::class, 'downloadAttachment'])->name('attachments.download')->whereNumber('attachment');
        Route::get('/{id}', [TicketAdminWebController::class, 'show'])->name('show')->whereNumber('id');
        Route::post('/{id}/ai/analyze', [TicketAdminWebController::class, 'aiAnalyze'])->name('ai.analyze')->whereNumber('id');
        Route::post('/{id}/ai/apply-priority', [TicketAdminWebController::class, 'aiApplyPriority'])->name('ai.apply-priority')->whereNumber('id');
        Route::post('/{id}/ai/suggest-response', [TicketAdminWebController::class, 'aiSuggestResponse'])->name('ai.suggest-response')->whereNumber('id');
        Route::post('/{id}/ai/drafts/{draftId}/review', [TicketAdminWebController::class, 'aiReviewDraft'])->name('ai.review-draft')->whereNumber('id')->whereNumber('draftId');
        Route::post('/{id}/start', [TicketAdminWebController::class, 'startProgress'])->name('start')->whereNumber('id');
        Route::post('/{id}/actions', [TicketAdminWebController::class, 'action'])->name('action')->whereNumber('id');
        Route::post('/{id}/feedback', [TicketAdminWebController::class, 'feedback'])->name('feedback')->whereNumber('id');
        Route::post('/{id}/escalate', [TicketAdminWebController::class, 'escalate'])->name('escalate')->whereNumber('id');
        Route::post('/{id}/close', [TicketAdminWebController::class, 'close'])->name('close')->whereNumber('id');
        Route::post('/{id}/attachments', [TicketAdminWebController::class, 'attach'])->name('attach')->whereNumber('id');
        Route::post('/{id}/reassign', [TicketAdminWebController::class, 'reassign'])->name('reassign')->whereNumber('id');
    });

    Route::middleware('can.manage-tickets')->prefix('/knowledge')->name('knowledge.')->group(function () {
        Route::get('/', [KnowledgeAdminWebController::class, 'dashboard'])->name('dashboard');
        Route::get('/documents', [KnowledgeAdminWebController::class, 'index'])->name('index');
        Route::get('/documents/create', [KnowledgeAdminWebController::class, 'create'])->name('create');
        Route::post('/documents', [KnowledgeAdminWebController::class, 'store'])->name('store');
        Route::get('/documents/{id}', [KnowledgeAdminWebController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('/documents/{id}/edit', [KnowledgeAdminWebController::class, 'edit'])->name('edit')->whereNumber('id');
        Route::put('/documents/{id}', [KnowledgeAdminWebController::class, 'update'])->name('update')->whereNumber('id');
        Route::post('/documents/{id}/toggle', [KnowledgeAdminWebController::class, 'toggle'])->name('toggle')->whereNumber('id');
        Route::post('/documents/{id}/reindex', [KnowledgeAdminWebController::class, 'reindex'])->name('reindex')->whereNumber('id');
        Route::get('/categories', [KnowledgeAdminWebController::class, 'categories'])->name('categories');
        Route::post('/categories', [KnowledgeAdminWebController::class, 'storeCategory'])->name('categories.store');
        Route::match(['get', 'post'], '/test', [KnowledgeAdminWebController::class, 'test'])->name('test');
        Route::get('/chat', [KnowledgeAdminWebController::class, 'chat'])->name('chat');
        Route::post('/chat', [KnowledgeAdminWebController::class, 'chatSend'])->name('chat.send');
        Route::get('/chat/{id}', [KnowledgeAdminWebController::class, 'chatShow'])->name('chat.show')->whereNumber('id');
        Route::delete('/chat/{id}', [KnowledgeAdminWebController::class, 'chatDestroy'])->name('chat.destroy')->whereNumber('id');
    });

    Route::get('/ast/wallet', [AstWalletAdminController::class, 'show'])->name('ast.wallet.show');
    Route::post('/ast/load', [AstWalletAdminController::class, 'load'])->name('ast.load');
    Route::get('/ast/cis-queue', [AstWalletAdminController::class, 'cisQueue'])->name('ast.cis.queue');
    Route::post('/ast/payments/{id}/post-cis', [AstWalletAdminController::class, 'postCis'])
        ->whereNumber('id')
        ->name('ast.cis.post');

    // ── AST Admin Blade UI ────────────────────────────────────────────────
    Route::prefix('/ast/admin')->name('ast.admin.')->group(function () {
        Route::get('/dashboard',   [AstAdminWebController::class, 'dashboard'])->name('dashboard');
        Route::get('/daily-chart', [AstAdminWebController::class, 'dailyChart'])->name('daily-chart');

        // Load AST (write access — canLoadWallet required, enforced in controller)
        Route::get('/load',  [AstAdminWebController::class, 'loadForm'])->name('load');
        Route::post('/load', [AstAdminWebController::class, 'loadSubmit'])->name('load.submit');
        Route::post('/adjust', [AstAdminWebController::class, 'adjustSubmit'])->name('adjust.submit');

        // Support request load (any staff can submit; wallet.load approves)
        Route::get('/request',  [AstAdminWebController::class, 'requestForm'])->name('request');
        Route::post('/request', [AstAdminWebController::class, 'requestSubmit'])->name('request.submit');

        // Customer search (AJAX, read-only)
        Route::get('/customer-search', [AstAdminWebController::class, 'customerSearch'])->name('customer-search');

        // Load requests / maker-checker queue
        Route::get('/load-requests',                     [AstAdminWebController::class, 'loadRequests'])->name('load-requests');
        Route::post('/load-requests/{id}/approve',       [AstAdminWebController::class, 'approveRequest'])->name('load-requests.approve')->whereNumber('id');
        Route::post('/load-requests/{id}/reject',        [AstAdminWebController::class, 'rejectRequest'])->name('load-requests.reject')->whereNumber('id');

        // Customer wallet detail (read-only for all staff, canLoad gates the "Load AST" button in view)
        Route::get('/customer/{userId}', [AstAdminWebController::class, 'customerWallet'])->name('customer-wallet')->whereNumber('userId');
    });

    Route::get('/api/user-name/{id}', function ($id) {
        $user = User::find($id);
        return response()->json([
            'name' => $user?->name,
            'avatar' => $user?->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null,
        ]);
    });

    Route::get('/complaints/create', [CustomerComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints/store', [CustomerComplaintController::class, 'store'])->name('complaints.store');

    Route::get('/pages', [pagesController::class, 'index']);
    Route::get('/pages/new', [pagesController::class, 'create']);

    Route::get('/ublog', [postController::class, 'index']);
    Route::get('/ublog/new', [postController::class, 'create']);
    Route::get('/ublog/edit/{id}', [postController::class, 'edit']);
    Route::post('/ublog/save', [postController::class, 'save'])->name('blog.save');
    Route::put('/ublog/update/{id}', [postController::class, 'update'])->name('blog.update');
    Route::post('/ublog/remove-pdf', [postController::class, 'removePdf'])->name('blog.remove_pdf');
    Route::get('/api/blogs/list', [postController::class, 'datatable'])->name('api.blogs.list');

    Route::post('/fetch-billing', [BillingApiController::class, 'getBillingData']);

    Route::get('users', fn () => redirect()->route('access.users.index'));
    Route::post('/users/store', [AccessUserWebController::class, 'store'])->name('users.save');
    Route::put('/users/{user}', [AccessUserWebController::class, 'update'])->name('users.update');
    Route::post('/api/users', [UserController::class, 'datatable'])->name('api.users');

    Route::prefix('/access')->name('access.')->group(function () {
        Route::get('/users', [AccessUserWebController::class, 'index'])->name('users.index');
        Route::get('/customers', [AccessUserWebController::class, 'customers'])->name('customers.index');
        Route::get('/customers/{user}', [AccessUserWebController::class, 'showCustomer'])->name('customers.show')->whereNumber('user');
        Route::get('/customers/{user}/membership-application', [AccessUserWebController::class, 'membershipApplication'])->name('customers.membership-application')->whereNumber('user');
        Route::get('/support', [AccessUserWebController::class, 'support'])->name('support.index');
        Route::get('/support/{user}', [AccessUserWebController::class, 'showSupport'])->name('support.show')->whereNumber('user');
        Route::post('/users', [AccessUserWebController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AccessUserWebController::class, 'update'])->name('users.update-full')->whereNumber('user');
        Route::post('/users/{user}/photo', [AccessUserWebController::class, 'photo'])->name('users.photo')->whereNumber('user');
        Route::delete('/users/{user}/photo', [AccessUserWebController::class, 'destroyPhoto'])->name('users.photo.destroy')->whereNumber('user');
        Route::post('/users/{user}/status', [AccessUserWebController::class, 'status'])->name('users.status')->whereNumber('user');
        Route::post('/users/{user}/reset', [AccessUserWebController::class, 'reset'])->name('users.reset')->whereNumber('user');
        Route::post('/users/{user}/password', [AccessUserWebController::class, 'password'])->name('users.password')->whereNumber('user');
        Route::get('/accounts/search', [AccessUserWebController::class, 'searchAccounts'])->name('users.accounts.search');
        Route::post('/users/{user}/account-links', [AccessUserWebController::class, 'storeAccountLink'])->name('users.account-links.store')->whereNumber('user');
        Route::post('/users/{user}/permissions', [AccessUserWebController::class, 'permissions'])->name('users.permissions')->whereNumber('user');
        Route::post('/users/bulk', [AccessUserWebController::class, 'bulk'])->name('users.bulk');
        Route::get('/users/export', [AccessUserWebController::class, 'export'])->name('users.export');
        Route::get('/users/import', [AccessUserWebController::class, 'importForm'])->name('users.import');
        Route::post('/users/import', [AccessUserWebController::class, 'importPreview'])->name('users.import.preview');
        Route::post('/users/import/commit', [AccessUserWebController::class, 'importCommit'])->name('users.import.commit');

        Route::get('/departments', [AccessOrgWebController::class, 'departments'])->name('departments.index');
        Route::get('/departments/{department}', [AccessOrgWebController::class, 'showDepartment'])->name('departments.show');
        Route::post('/departments', [AccessOrgWebController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [AccessOrgWebController::class, 'updateDepartment'])->name('departments.update');
        Route::get('/roles', [AccessOrgWebController::class, 'roles'])->name('roles.index');
        Route::post('/roles', [AccessOrgWebController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [AccessOrgWebController::class, 'updateRole'])->name('roles.update');
        Route::get('/permissions', [AccessOrgWebController::class, 'permissions'])->name('permissions.index');
        Route::put('/permissions/roles/{role}', [AccessOrgWebController::class, 'updateRolePermissions'])->name('permissions.roles.update');
        Route::get('/sessions', [AccessOrgWebController::class, 'sessions'])->name('sessions.index');
        Route::post('/sessions/{id}/revoke', [AccessOrgWebController::class, 'revokeSession'])->name('sessions.revoke');
        Route::post('/users/{user}/sessions/revoke', [AccessOrgWebController::class, 'revokeUserSessions'])->name('sessions.revoke-user')->whereNumber('user');
        Route::get('/activity', [AccessOrgWebController::class, 'activity'])->name('activity.index');
        Route::get('/availability', [AccessOrgWebController::class, 'availability'])->name('availability.index');
        Route::post('/availability/{user}', [AccessOrgWebController::class, 'updateAvailability'])->name('availability.update')->whereNumber('user');
        Route::get('/assignments', [AccessOrgWebController::class, 'assignments'])->name('assignments.index');
        Route::get('/assignments/{ticket}/history', [AccessOrgWebController::class, 'assignmentHistory'])->name('assignments.history')->whereNumber('ticket');
        Route::get('/settings', [AccessOrgWebController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [AccessOrgWebController::class, 'updateSettings'])->name('settings.update');
        Route::get('/reports', [AccessOrgWebController::class, 'reports'])->name('reports.index');
    });

    Route::middleware('can.permission:tickets.view')->prefix('/workspace')->name('workspace.')->group(function () {
        Route::get('/dashboard', [SupportWorkspaceWebController::class, 'dashboard'])->name('dashboard');
        Route::get('/supervisor', [SupportWorkspaceWebController::class, 'supervisor'])->name('supervisor');
        Route::get('/tickets', [SupportWorkspaceWebController::class, 'myTickets'])->name('tickets');
        Route::get('/department-queue', [SupportWorkspaceWebController::class, 'departmentQueue'])->name('department');
        Route::get('/sla', [SupportWorkspaceWebController::class, 'sla'])->name('sla');
        Route::get('/notifications', [SupportWorkspaceWebController::class, 'notifications'])->name('notifications');
    });

    Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
    Route::get('menus/{menu}/builder', [MenuController::class, 'builder'])->name('menus.builder');
    Route::patch('menus/{menu}/builder', [MenuController::class, 'update'])->name('menus.builder');

    // Existing endpoints from earlier
    Route::resource('menus', MenuController::class)
        ->only(['store', 'show', 'update', 'destroy', 'create'])
        ->middleware([]);

    // Menu item CRUD
    Route::post('menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
    Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update'])->name('menu-items.update');
    Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->name('menu-items.destroy');

    // NEW: save full tree (parent_id + order)
    Route::post('menu-items/save-tree', [MenuItemController::class, 'saveTree'])->name('menu-items.saveTree');

    Route::get('pages', [MenuController::class, 'pages'])->name('menus.index');
    Route::post('/api/pages/list', [MenuController::class, 'api_pages'])->name('api.pages');

    Route::prefix('/file-manager')->group(function () {
        Route::get('/list', [FileManagerController::class, 'index'])->name('filemanager.index');
        Route::get('/list/folder', [FileManagerController::class, 'index'])->name('filemanager.index');
        Route::get('/storage', [FileManagerController::class, 'index'])->name('filemanager.index');
        Route::get('/{parent_id?}', [FileManagerController::class, 'xindex'])->name('filemanager.index');
        Route::get('/folder/privacy', [FileManagerController::class, 'updatePrivacy'])->name('folder.privacy');
        Route::get('/folder/file/{url}', [FileManagerController::class, 'preview'])->name('folder.preview.file');

        Route::post('/upload', [FileManagerController::class, 'store'])->name('filemanager.store');
        Route::post('/create/folder', [FileManagerController::class, 'folder'])->name('filemanager.folder');
        Route::post('/submit', [FileManagerController::class, 'submitted'])->name('filemanager.submitted');
        Route::post('/rename/{id}', [FileManagerController::class, 'rename'])->name('filemanager.rename');

        Route::post('/api/files', [FileManagerController::class, 'api_files'])->name('api.filemanager.files');

        Route::delete('/{file}', [FileManagerController::class, 'destroy'])->name('filemanager.destroy');
    });

    Route::get('/file-manager/v2/list', [FileManagerController::class, 'index_v2']); // demo page
    Route::post('/drive/file/upload-chunk', [ChunkUploadController::class, 'chunk'])->name('api.drive.file.upload-chunk');
    // ---------------- Drive uploads
    Route::get('/google-drive', fn(Request $r) => view('pages.filemanager.upload'));
    Route::post('/google-drive-actived', [GoogleDriveController::class, 'activated'])->name('drive.folder.activated');
    Route::post('/drive/folder/create', [GoogleDriveController::class, 'folder'])->name('drive.folder.create');
    Route::get('/drive/storage', [GoogleDriveController::class, 'getStorageInfo']);
    Route::post('/drive/file/upload', [GoogleDriveController::class, 'upload'])->name('drive.file.upload');

    Route::post('/delete', function (Request $request) {
        $id = $request->id;
        $type = $request->type;

        FileManager::where('id', $id)->update(['isDeleted' => 1]);
    })->name('trash.bin');
});
