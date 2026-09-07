<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\AccountLink;
use App\Models\AppNotification;
use App\Models\AstWallet;
use App\Models\Department;
use App\Models\MemberProfile;
use App\Models\Role;
use App\Models\TAccountRaw;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Access\AccessService;
use App\Services\Access\UserManagementService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccessUserWebController extends Controller
{
    public function __construct(
        private AccessService $access,
        private UserManagementService $users,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assert($request, 'users.view');

        return $this->list($request, null, 'Users', 'access.users.index');
    }

    public function customers(Request $request): View
    {
        $this->assert($request, 'customers.view');

        return $this->list($request, 'customer', 'Customers', 'access.customers.index');
    }

    public function support(Request $request): View
    {
        $this->assert($request, 'users.view');

        return $this->list($request, 'support', 'Support Accounts', 'access.support.index');
    }

    public function showCustomer(Request $request, User $user): View
    {
        $this->assert($request, 'customers.view');
        if (! $this->access->customerVisible($request->user(), $user)) {
            abort(403, 'You cannot view this customer.');
        }

        return view('pages.staff.access.user-show', $this->detailPayload($user));
    }

    public function membershipApplication(Request $request, User $user): View
    {
        $this->assert($request, 'customers.view');
        if (! $this->access->customerVisible($request->user(), $user)) {
            abort(403, 'You cannot view this customer.');
        }

        $user->loadMissing(['memberProfile']);
        $accountLink = Schema::hasTable('account_links')
            ? AccountLink::query()->where('user_id', $user->id)->latest()->first()
            : null;

        return view('pages.staff.access.membership-application', [
            'user' => $user,
            'profile' => $user->memberProfile,
            'accountLink' => $accountLink,
        ]);
    }

    public function showSupport(Request $request, User $user): View
    {
        $this->assert($request, 'users.view');

        return view('pages.staff.access.user-show', $this->detailPayload($user));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assert($request, 'users.create');
        $validated = $request->validate($this->rules());
        $validated['send_credentials'] = true;
        $this->users->create($request->user(), $validated);

        return back()->with('success', 'Account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.edit');
        $this->users->update($request->user(), $user, $request->validate($this->rules($user->id)));

        return back()->with('success', 'Account updated.');
    }

    public function photo(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.edit');
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $this->users->updatePhoto($request->user(), $user, $validated['photo']);

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.edit');
        $this->users->deletePhoto($request->user(), $user);

        return back()->with('success', 'Profile photo removed.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.suspend');
        $this->users->setStatus($request->user(), $user, (string) $request->validate([
            'account_status' => 'required|string',
        ])['account_status']);

        return back()->with('success', 'Account status updated.');
    }

    public function reset(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.edit');
        $password = $this->users->resetAccess($request->user(), $user);

        return back()->with('success', 'Access reset. Temporary password: '.$password);
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.edit');

        $mode = $request->input('mode') === 'generate' ? 'generate' : 'manual';
        $plain = null;

        if ($mode === 'manual' || $request->filled('password')) {
            $plain = $request->validate($this->passwordRules())['password'];
        }

        $password = $this->users->changePassword($request->user(), $user, $plain);

        return back()->with('success', 'Password updated. New password: '.$password);
    }

    public function storeAccountLink(Request $request, User $user): RedirectResponse
    {
        if (! $this->access->allows($request->user(), 'users.edit')
            && ! $this->access->allows($request->user(), 'customers.edit')) {
            abort(403);
        }

        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'validate_now' => ['nullable', 'boolean'],
        ]);

        try {
            $this->users->addAccountLink(
                $request->user(),
                $user,
                $validated['account_number'],
                $validated['owner_name'] ?? null,
                $request->boolean('validate_now'),
            );
        } catch (HttpException $e) {
            return back()->withInput()->withErrors([
                'account_number' => $e->getMessage(),
            ]);
        }

        return back()->with('success', $request->boolean('validate_now')
            ? 'Service account linked and validated.'
            : 'Account link request added.');
    }

    public function searchAccounts(Request $request): JsonResponse
    {
        if (! $this->access->allows($request->user(), 'users.edit')
            && ! $this->access->allows($request->user(), 'customers.edit')
            && ! $this->access->allows($request->user(), 'customers.view')
            && ! $this->access->allows($request->user(), 'users.view')) {
            abort(403);
        }

        $q = trim((string) $request->query('q', ''));
        $minLength = $this->isAccountNumberQuery($q) ? 2 : 3;
        if (mb_strlen($q) < $minLength || ! Schema::hasTable('t_accounts_raw')) {
            return response()->json(['data' => []]);
        }

        $query = TAccountRaw::query();
        if (Schema::hasColumn('t_accounts_raw', 'isDeleted')) {
            $query->where(function ($inner): void {
                $inner->where('isDeleted', 0)->orWhereNull('isDeleted');
            });
        }

        // Prefix matches can use indexes. Leading-wildcard LIKE + ORDER BY LOWER()
        // on t_accounts_raw full-scans the CIS table and times out.
        if ($this->isAccountNumberQuery($q)) {
            $query->where('account_no', 'like', $q.'%');
        } elseif (Schema::hasColumn('t_accounts_raw', 'customer')) {
            $query->where('customer', 'like', $q.'%');
        } else {
            $query->where('account_no', 'like', $q.'%');
        }

        $needle = mb_strtolower($q);
        $rows = $query
            ->limit(40)
            ->get()
            ->sortBy(function (TAccountRaw $row) use ($needle) {
                $account = mb_strtolower((string) $row->account_no);
                $customer = mb_strtolower((string) ($row->customer ?? ''));
                if ($account === $needle) {
                    return 0;
                }
                if (str_starts_with($account, $needle)) {
                    return 1;
                }
                if (str_starts_with($customer, $needle)) {
                    return 2;
                }

                return 3;
            })
            ->take(12)
            ->values()
            ->map(fn (TAccountRaw $row) => [
                'account_no' => (string) $row->account_no,
                'customer' => (string) ($row->customer ?? ''),
                'meter_no' => (string) ($row->meter_no ?? ''),
                'address' => (string) ($row->address ?? ''),
                'rate_class' => (string) ($row->rate_class ?? ''),
                'status' => (string) ($row->status ?? ''),
                'linked' => filled($row->user_id),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->assert($request, 'users.edit');
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:users,id',
            'account_status' => 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'role_id' => 'nullable|integer|exists:roles,id',
            'availability_status' => 'nullable|string',
        ]);
        $count = $this->users->bulk($request->user(), $validated['ids'], $validated);

        return back()->with('success', $count.' account(s) updated.');
    }

    public function permissions(Request $request, User $user): RedirectResponse
    {
        $this->assert($request, 'users.permissions.manage');
        $overrides = [];
        foreach ((array) $request->input('overrides', []) as $code => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $overrides[$code] = $value === '1' || $value === 1 || $value === true;
        }
        $this->users->syncUserPermissions($request->user(), $user, $overrides);

        return back()->with('success', 'User permissions updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->assert($request, 'users.view');
        $query = User::query()->with(['accessRole', 'accessDepartment']);
        if ($request->query('user_type')) {
            $query->where('user_type', $request->query('user_type'));
        }

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'username', 'role', 'department', 'status', 'availability', 'user_type']);
            $query->orderBy('name')->each(function (User $user) use ($out) {
                fputcsv($out, [
                    $user->name,
                    $user->email,
                    $user->username,
                    $user->accessRole?->code ?? $user->role,
                    $user->accessDepartment?->code ?? $user->department_code,
                    $user->account_status,
                    $user->availability_status,
                    $user->user_type,
                ]);
            });
            fclose($out);
        }, 'users.csv');
    }

    public function importForm(): View
    {
        $this->assert(request(), 'users.create');

        return view('pages.staff.access.import', ['preview' => null]);
    }

    public function importPreview(Request $request): View
    {
        $this->assert($request, 'users.create');
        $request->validate(['file' => 'required|file']);
        $rows = $this->parseCsv($request->file('file')->getRealPath());
        $preview = $this->users->previewImport($rows);
        $request->session()->put('user_import_preview', $preview);

        return view('pages.staff.access.import', ['preview' => $preview]);
    }

    public function importCommit(Request $request): RedirectResponse
    {
        $this->assert($request, 'users.create');
        $preview = $request->session()->pull('user_import_preview', []);
        $count = $this->users->commitImport($request->user(), $preview);

        return redirect()->route('access.users.index')->with('success', $count.' account(s) imported.');
    }

    private function list(Request $request, ?string $type, string $title, string $route): View
    {
        $list = ListQuery::from(
            $request,
            filterKeys: ['role_id', 'department_id', 'account_status', 'availability_status', 'user_type'],
            sortable: ['name' => 'name', 'email' => 'email', 'created_at' => 'created_at', 'last_login_at' => 'last_login_at', 'id' => 'id'],
            defaultSort: 'name',
            defaultDir: 'asc',
        );

        $query = User::query()->with(['accessRole', 'accessDepartment']);
        if ($type === 'customer' && Schema::hasTable('member_profiles')) {
            $query->with('memberProfile');
        }
        if ($type) {
            $query->where('user_type', $type);
        }
        if ($list['search']) {
            $this->applyUserListSearch($query, $list['search']);
        }
        foreach (['role_id', 'department_id', 'account_status', 'availability_status', 'user_type'] as $key) {
            if (! empty($list['filters'][$key])) {
                $query->where($key, $list['filters'][$key]);
            }
        }

        $users = ListQuery::applySort($query, $list, [
            'name' => 'name', 'email' => 'email', 'created_at' => 'created_at', 'last_login_at' => 'last_login_at', 'id' => 'id',
        ])->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.access.users-index', [
            'title' => $title,
            'routeName' => $route,
            'users' => $users,
            'list' => $list,
            'filters' => array_merge($list['filters'], ['search' => $list['search'], 'sort' => $list['sort'], 'dir' => $list['dir']]),
            'activeFilterCount' => $list['active_filter_count'],
            'roles' => Role::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
            'userType' => $type,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(User $user, bool $support = false): array
    {
        $isCustomer = ($user->user_type ?? '') === 'customer';
        $support = $support || ! $isCustomer;
        $tickets = Ticket::query()->where(function ($q) use ($user, $support) {
            if ($support) {
                $q->where('assigned_to', $user->id);
            } else {
                $q->where('customer_id', $user->id);
            }
        });

        $recentTickets = (clone $tickets)
            ->with(['category', 'assignee', 'customer'])
            ->latest()
            ->limit(12)
            ->get();

        $activity = UserActivityLog::query()
            ->with('user')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_id', $user->id)
                            ->where('target_type', User::class);
                    });
            })
            ->latest('created_at')
            ->limit(20)
            ->get();

        $sessions = Schema::hasTable('sessions')
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->limit(15)->get()
            : collect();

        $accountLinks = Schema::hasTable('account_links')
            ? AccountLink::query()->where('user_id', $user->id)->latest()->get()
            : collect();

        if (Schema::hasTable('member_profiles')) {
            $user->loadMissing('memberProfile');
        }

        $astWallets = Schema::hasTable('ast_wallets')
            ? AstWallet::query()->where('user_id', $user->id)->orderBy('account_number')->get()
            : collect();

        $notifications = AppNotification::query()->where('user_id', $user->id)->latest()->limit(12)->get();

        return [
            'user' => $user->load(['accessRole.permissions', 'accessDepartment', 'supervisor', 'skills', 'staffAvailability', 'staffProfile', 'permissionOverrides.permission']),
            'accounts' => Schema::hasTable('t_accounts_raw')
                ? TAccountRaw::query()->where('user_id', $user->id)->get()
                : collect(),
            'accountLinks' => $accountLinks,
            'memberProfile' => $user->memberProfile ?? null,
            'wallet' => Schema::hasTable('wallets')
                ? Wallet::query()->where('customer_id', $user->id)->first()
                : null,
            'astWallets' => $astWallets,
            'recentTickets' => $recentTickets,
            'sessions' => $sessions,
            'ticketCounts' => [
                'total' => (clone $tickets)->count(),
                'open' => (clone $tickets)->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])->count(),
                'new' => (clone $tickets)->where('status', Ticket::STATUS_NEW)->count(),
                'pending' => (clone $tickets)->where('status', Ticket::STATUS_ASSIGNED)->count(),
                'in_progress' => (clone $tickets)->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
                'escalated' => (clone $tickets)->where('status', Ticket::STATUS_ESCALATED)->count(),
                'resolved' => (clone $tickets)->where('status', Ticket::STATUS_RESOLVED)->count(),
                'closed' => (clone $tickets)->where('status', Ticket::STATUS_CLOSED)->count(),
                'sla' => (clone $tickets)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])->count(),
            ],
            'activity' => $activity,
            'notifications' => $notifications,
            'unreadNotifications' => $notifications->whereNull('read_at')->count(),
            'roles' => Role::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
            'matrix' => $this->access->codesToMatrix($this->access->effectiveCodes($user)),
            'support' => $support,
            'isCustomer' => $isCustomer,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function rules(?int $userId = null): array
    {
        $unique = $userId ? ','.$userId : '';

        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email'.$unique,
            'username' => 'nullable|string|max:60|unique:users,username'.$unique,
            'contact_no' => 'nullable|string|max:40',
            'employee_ref' => 'nullable|string|max:60',
            'position' => 'nullable|string|max:80',
            'role_id' => 'nullable|integer|exists:roles,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'supervisor_id' => 'nullable|integer|exists:users,id',
            'user_type' => 'nullable|in:customer,support',
            'account_status' => 'nullable|string',
            'availability_status' => 'nullable|string',
            'email_validated' => 'nullable|boolean',
            'skills' => 'nullable|array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passwordRules(): array
    {
        $min = (int) $this->access->setting('password_min_length', config('access.password_min_length', 8));
        $rule = Password::min($min);
        if ($this->access->setting('password_require_mixed', config('access.password_require_mixed', false))) {
            $rule = $rule->mixedCase()->numbers();
        }

        return [
            'password' => ['required', 'string', $rule, 'confirmed'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, array_pad($line, count($header), '')) ?: [];
        }
        fclose($handle);

        return $rows;
    }

    private function applyUserListSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $linkedIds = mb_strlen($term) >= 2
            ? $this->matchingUserIdsForSearch($term)
            : [];

        $query->where(function (Builder $q) use ($like, $linkedIds) {
            $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhere('role', 'like', $like)
                ->orWhere('department_code', 'like', $like)
                ->orWhereHas('accessRole', fn ($role) => $role->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orWhereHas('accessDepartment', fn ($dept) => $dept->where('name', 'like', $like)->orWhere('code', 'like', $like));

            if (Schema::hasColumn('users', 'contact_no')) {
                $q->orWhere('contact_no', 'like', $like);
            }
            if (Schema::hasColumn('users', 'employee_ref')) {
                $q->orWhere('employee_ref', 'like', $like);
            }
            if ($linkedIds !== []) {
                $q->orWhereIn('id', $linkedIds);
            }
        });
    }

    /**
     * Resolve matching portal users from small tables / indexable CIS prefixes.
     * Never correlated-EXISTS against t_accounts_raw with leading-wildcard LIKE.
     *
     * @return list<int>
     */
    private function matchingUserIdsForSearch(string $term): array
    {
        $like = '%'.$term.'%';
        $ids = [];

        if (Schema::hasTable('account_links')) {
            $ids = array_merge($ids, AccountLink::query()
                ->where(function ($links) use ($term, $like) {
                    $links->where('account_number', 'like', $term.'%')
                        ->orWhere('owner_name', 'like', $like);
                })
                ->limit(300)
                ->pluck('user_id')
                ->all());
        }

        if (Schema::hasTable('member_profiles')) {
            $ids = array_merge($ids, MemberProfile::query()
                ->where(function ($profile) use ($like) {
                    $profile->where('address', 'like', $like)
                        ->orWhere('barangay_name', 'like', $like)
                        ->orWhere('city_municipality_name', 'like', $like)
                        ->orWhere('province_name', 'like', $like)
                        ->orWhere('contact_no', 'like', $like);
                })
                ->limit(300)
                ->pluck('user_id')
                ->all());
        }

        if (
            $this->isAccountNumberQuery($term)
            && Schema::hasTable('t_accounts_raw')
            && Schema::hasColumn('t_accounts_raw', 'user_id')
        ) {
            $ids = array_merge($ids, TAccountRaw::query()
                ->whereNotNull('user_id')
                ->where('account_no', 'like', $term.'%')
                ->limit(300)
                ->pluck('user_id')
                ->all());
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $ids),
            static fn (int $id) => $id > 0,
        )));
    }

    private function isAccountNumberQuery(string $term): bool
    {
        return (bool) preg_match('/^[0-9][0-9\-]*$/', $term);
    }

    private function assert(Request $request, string $permission): void
    {
        if (! $this->access->allows($request->user(), $permission)) {
            abort(403);
        }
    }
}
