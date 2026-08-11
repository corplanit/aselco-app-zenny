<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AccountLink;
use App\Models\TAccountRaw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;



class AccountLinkController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|numeric',
            'owner_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        AccountLink::create([
            'user_id' => Auth::id(),
            'account_number' => $request->account_number,
            'owner_name' => strtoupper($request->owner_name),
            'validated_at' => null,
            'validated_by' => null,
        ]);

        return redirect()->back()->with('success', 'Account successfully linked and validated.');
    }

    public function update(Request $request)
    {
        // ✅ BULK MODE (checkbox)
        if ($request->filled('ids') && is_array($request->ids)) {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|distinct|exists:account_links,id',
            ]);

            if ($validator->fails()) {
                return $request->ajax() ? response()->json(['ok' => false, 'errors' => $validator->errors()], 422) : redirect()->back()->withErrors($validator)->withInput();
            }

            $ids = collect($request->ids)->map(fn($v) => (int) $v)->values()->all();

            $result = DB::transaction(function () use ($ids) {
                $now = Carbon::now();
                $by = Auth::user()->name;

                // Load accounts to update
                $accounts = AccountLink::whereIn('id', $ids)->get();

                $updatedCount = 0;

                foreach ($accounts as $account) {
                    // Optional: skip if already validated/linked (adjust rules if you want to re-validate)
                    if (!empty($account->validated_at) || ($account->status ?? null) === 'Linked') {
                        continue;
                    }

                    $account->update([
                        'validated_at' => $now,
                        'validated_by' => $by,
                        'status' => 'Linked', // ✅ if you have status column
                    ]);

                    // Update related raw account
                    TAccountRaw::where('account_no', $account->account_number)->update([
                        'user_id' => $account->user_id,
                        'status' => 'Linked',
                    ]);

                    $updatedCount++;
                }

                return [
                    'updated' => $updatedCount,
                    'total' => count($ids),
                ];
            });

            return $request->ajax()
                ? response()->json([
                    'ok' => true,
                    'message' => "Bulk link complete. Updated {$result['updated']} of {$result['total']}.",
                    'meta' => $result,
                ])
                : redirect()
                    ->back()
                    ->with('success', "Bulk link complete. Updated {$result['updated']} of {$result['total']}.");
        }

        // ✅ SINGLE MODE (existing behavior)
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:account_links,id',
        ]);

        if ($validator->fails()) {
            return $request->ajax() ? response()->json(['ok' => false, 'errors' => $validator->errors()], 422) : redirect()->back()->withErrors($validator)->withInput();
        }

        $account = AccountLink::findOrFail($request->id);

        DB::transaction(function () use ($account) {
            $account->update([
                'validated_at' => Carbon::now(),
                'validated_by' => Auth::user()->name,
                'status' => 'Linked', // ✅ if you have status column
            ]);

            TAccountRaw::where('account_no', $account->account_number)->update([
                'user_id' => $account->user_id,
                'status' => 'Linked',
            ]);
        });

        return $request->ajax() ? response()->json(['ok' => true, 'message' => 'Account successfully linked and validated.']) : redirect()->back()->with('success', 'Account successfully linked and validated.');
    }
}
