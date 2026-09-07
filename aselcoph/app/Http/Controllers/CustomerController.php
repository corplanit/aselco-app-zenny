<?php

namespace App\Http\Controllers;

use App\Models\blogModel;
use App\Models\TAccountRaw;
use App\Support\ListQuery;
use Illuminate\Http\Request;

// composer require yajra/laravel-datatables-oracle
use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $sortable = [
            'account_no' => 't_accounts_raw.account_no',
            'customer' => 't_accounts_raw.customer',
            'status' => 't_accounts_raw.status',
            'email' => 'users.email',
        ];
        $list = ListQuery::from($request, ['status', 'portal'], $sortable, 'customer', 'asc');

        $query = TAccountRaw::query()
            ->from('t_accounts_raw')
            ->where('t_accounts_raw.isDeleted', 0)
            ->leftJoin('users', 't_accounts_raw.user_id', '=', 'users.id')
            ->select([
                't_accounts_raw.account_no',
                't_accounts_raw.customer',
                't_accounts_raw.user_id',
                't_accounts_raw.status',
                'users.email',
                'users.contact_no as contact',
            ]);

        if ($list['search'] && mb_strlen($list['search']) >= 2) {
            $term = $list['search'];
            $contains = '%'.$term.'%';
            $prefix = $term.'%';
            $accountLike = (bool) preg_match('/^[0-9][0-9\-]*$/', $term);
            $query->where(function ($q) use ($contains, $prefix, $accountLike) {
                if ($accountLike) {
                    $q->where('t_accounts_raw.account_no', 'like', $prefix);
                } else {
                    $q->where('t_accounts_raw.customer', 'like', $prefix)
                        ->orWhere('users.email', 'like', $contains)
                        ->orWhere('users.contact_no', 'like', $contains);
                }
            });
        }

        if (! empty($list['filters']['status'])) {
            $query->where('t_accounts_raw.status', $list['filters']['status']);
        }

        if (($list['filters']['portal'] ?? '') === 'linked') {
            $query->whereNotNull('t_accounts_raw.user_id');
        } elseif (($list['filters']['portal'] ?? '') === 'unlinked') {
            $query->whereNull('t_accounts_raw.user_id');
        }

        ListQuery::applySort($query, $list, $sortable);

        $statuses = TAccountRaw::query()
            ->where('isDeleted', 0)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('pages.customer.index', [
            'consumers' => $query->paginate($list['per_page'])->withQueryString(),
            'list' => $list,
            'filters' => array_merge($list['filters'], ['search' => $list['search']]),
            'activeFilterCount' => $list['active_filter_count'],
            'statuses' => $statuses->isEmpty() ? collect(['Linked', 'Inactive']) : $statuses,
        ]);
    }

    public static function create()
    {
        return view('pages.customer.registration');
    }

    public static function store(Request $request)
    {
        $request->validate([
            'account_no' => 'required|string|max:50',
            'consumer' => 'required|string|max:255',
        ]);

        // Check if account already exists
        $existing = TAccountRaw::where('account_no', $request->account_no)->first();

        if ($existing) {
            return response()->json(
                [
                    'error' => 'This account number already exists.',
                ],
                409,
            ); // Conflict
        }

        // Create new raw account
        TAccountRaw::create([
            'account_no' => $request->account_no,
            'customer' => $request->consumer,
            'status' => 'Inactive', // default
        ]);

        return redirect()->back()->with('success', 'New account created successfully.');

    }

    public function updateAccount(Request $request)
    {
        $request->validate([
            'account_no' => 'required|exists:t_accounts_raw,account_no',
            'customer' => 'required|string|max:255',
            'email' => 'required|email',
            'contact' => 'required|string|max:20',
        ]);

        $account = TAccountRaw::where('account_no', $request->account_no)->first();

        if (!$account || !$account->user_id) {
            return response()->json(['error' => 'Linked user account not found.'], 404);
        }

        $user = User::find($account->user_id);
        if (!$user) {
            return response()->json(['error' => 'User record does not exist.'], 404);
        }

        // Update account and user details
        $account->customer = $request->customer;
        $account->save();

        $user->email = $request->email;
        $user->contact_no = $request->contact;
        $user->save();

        return response()->json(['message' => 'Account and user details updated successfully.']);
    }

    public static function list(Request $request)
    {
        try {
            if ($request->ajax()) {
                $query = TAccountRaw::query()->where('t_accounts_raw.isDeleted', 0)->leftJoin('users', 't_accounts_raw.user_id', '=', 'users.id')->select('t_accounts_raw.*', 'users.email as email', 'users.contact_no as contact', 'users.id as user_id');

                return DataTables::of($query)
                    ->filterColumn('email', function ($query, $keyword) {
                        $query->whereRaw('LOWER(users.email) LIKE ?', ['%' . strtolower($keyword) . '%']);
                    })
                    ->filterColumn('contact', function ($query, $keyword) {
                        $query->whereRaw('LOWER(users.contact_no) LIKE ?', ['%' . strtolower($keyword) . '%']);
                    })
                    ->make(true);
            }
            return response()->json(['error' => 'Invalid request.'], 400); // Fallback for non-AJAX
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createAccountForRaw(Request $request)
    {
        $request->validate([
            'account_no' => 'required',
            'consumer' => 'required',
            'email' => 'required|email|unique:users,email',
            'contact' => 'required',
        ]);

        $account = TAccountRaw::where('account_no', $request->account_no)->first();

        if (!$account || $account->status !== 'Inactive') {
            return response()->json(['error' => 'Account not found or already active.'], 400);
        }

        // Generate secure password
        $password = Str::random(10);
        $hashedPassword = Hash::make($password);

        // Create new user
        $user = User::create([
            'name' => $request->consumer,
            'email' => $request->email,
            'contact_no' => $request->contact,
            'password' => $hashedPassword,
        ]);

        // Link user to raw account
        $account->user_id = $user->id; 
        $account->status = 'Linked';
        $account->save();

        // Send email
        Mail::send(
            'emails.create-account',
            [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ],
            function ($message) use ($user) {
                $message->to($user->email)->subject('Your Portal Account Credentials');
            },
        );

        return response()->json(['message' => 'User account created and linked successfully.']);
    }

    public function linkToExistingUser(Request $request)
    {
        $request->validate([
            'account_no' => 'required|exists:t_accounts_raw,account_no',
            'user_id' => 'required|exists:users,id',
        ]);

        $account = TAccountRaw::where('account_no', $request->account_no)->first();

        $account->user_id = $request->user_id;
        $account->status = 'Linked'; // Optional
        $account->save();

        return response()->json(['message' => 'Account successfully linked to existing user.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::find($request->user_id);
        $user->password = Hash::make($request->password);
        $user->save();

        // Send email notification
        Mail::send(
            'emails.password-changed',
            [
                'name' => $user->name,
            ],
            function ($message) use ($user) {
                $message->to($user->email, $user->name)->subject('Your ASELCO Portal Password Was Changed');
            },
        );

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        $newPassword = Str::random(10);
        $user->password = Hash::make($newPassword);
        $user->save();

        // Send email
        Mail::send(
            'emails.reset-password',
            [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $newPassword,
            ],
            function ($message) use ($user) {
                $message->to($user->email)->subject('Your Password Has Been Reset');
            },
        );

        return response()->json(['message' => 'Password reset and sent to email.']);
    }
}
