<?php

namespace App\Http\Controllers;

use App\Models\TicketCategory;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertCanManageUsers($request->user());

        $list = ListQuery::from(
            $request,
            filterKeys: ['role', 'verified', 'department_code'],
            sortable: [
                'name' => 'name',
                'email' => 'email',
                'created_at' => 'created_at',
                'id' => 'id',
            ],
            defaultSort: 'name',
            defaultDir: 'asc',
            defaultPerPage: 25,
        );

        $query = User::query();
        if ($list['search']) {
            $term = $list['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('role', 'like', "%{$term}%")
                    ->orWhere('department_code', 'like', "%{$term}%");
            });
        }
        if (! empty($list['filters']['role'])) {
            $query->where('role', $list['filters']['role']);
        }
        if (! empty($list['filters']['department_code'])) {
            $query->where('department_code', $list['filters']['department_code']);
        }
        if (($list['filters']['verified'] ?? null) === '1') {
            $query->whereNotNull('email_verified_at');
        } elseif (($list['filters']['verified'] ?? null) === '0') {
            $query->whereNull('email_verified_at');
        }

        $sortCol = in_array($list['sort'], ['name', 'email', 'created_at', 'id'], true) ? $list['sort'] : 'name';
        $users = $query->orderBy($sortCol, $list['dir'])->paginate($list['per_page'])->withQueryString();

        return view('users.index', [
            'users' => $users,
            'list' => $list,
            'filters' => array_merge($list['filters'], [
                'search' => $list['search'],
                'sort' => $list['sort'],
                'dir' => $list['dir'],
            ]),
            'activeFilterCount' => $list['active_filter_count'],
            'roles' => collect(User::managedRoles())
                ->merge(User::query()->whereNotNull('role')->distinct()->pluck('role'))
                ->unique()
                ->sort()
                ->values(),
            'managedRoles' => User::managedRoles(),
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $this->assertCanManageUsers($request->user());

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:'.implode(',', User::managedRoles()),
            'department_code' => 'nullable|string|max:40',
        ]);

        $rawPassword = Str::random(10);

        $newUser = $user->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'department_code' => filled($validated['department_code'] ?? null) ? $validated['department_code'] : null,
            'password' => Hash::make($rawPassword),
        ]);

        Mail::raw("Welcome to the system.\n\nYour login credentials:\nEmail: {$newUser->email}\nPassword: {$rawPassword}", function ($message) use ($newUser) {
            $message->to($newUser->email)->subject('Your Account Details');
        });

        return redirect()->back()->with('success', 'Account created and credentials sent to email.');
    }

    public function update(Request $request, User $user)
    {
        $this->assertCanManageUsers($request->user());

        $allowedRoles = array_values(array_unique(array_filter(array_merge(
            User::managedRoles(),
            [(string) $user->role],
        ))));

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'role' => 'required|in:'.implode(',', $allowedRoles),
            'department_code' => 'nullable|string|max:40',
            'email_validated' => 'nullable|in:0,1',
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'department_code' => filled($validated['department_code'] ?? null) ? $validated['department_code'] : null,
        ];

        if ($request->has('email_validated')) {
            $payload['email_verified_at'] = $request->boolean('email_validated')
                ? ($user->email_verified_at ?? now())
                : null;
        }

        $user->update($payload);

        return redirect()->back()->with('success', 'Account Updated successfully.');
    }

    public function datatable(Request $request)
    {
        $this->assertCanManageUsers($request->user());

        return response()->json([
            'data' => User::select(['id', 'name', 'email', 'role', 'department_code', 'created_at', 'email_verified_at'])
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'department_code' => $user->department_code,
                        'email_verified_at' => $user->email_verified_at,
                        'created_at' => $user->created_at->format('Y-m-d'),
                        'status' => 'Active',
                    ];
                }),
        ]);
    }

    /**
     * @return list<string>
     */
    private function departmentOptions(): array
    {
        $fromCategories = TicketCategory::query()->pluck('department_code')->all();

        return collect(array_merge($fromCategories, [
            config('tickets.assignment.distribution_night_department', 'GUARD'),
            config('tickets.assignment.tsd_department', 'TSD'),
            config('tickets.escalation.second_tier_department', 'AREA-ADMIN'),
        ]))->filter()->unique()->sort()->values()->all();
    }

    private function assertCanManageUsers(?User $user): void
    {
        if ($user === null || ! $user->isTicketAdmin()) {
            abort(403, 'Only administrators can manage users.');
        }
    }
}
