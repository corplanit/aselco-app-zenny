<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Models\WalletAuditLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator as IlluminateValidator;

class LoadWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canLoadWallet() === true;
    }

    protected function prepareForValidation(): void
    {
        $key = trim((string) $this->header('Idempotency-Key', ''));
        if ($key !== '') {
            $this->merge(['idempotency_key' => $key]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (float) config('ast.max_load_amount', 100000);

        return [
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$max],
            'reference_no' => ['required', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function withValidator(IlluminateValidator $validator): void
    {
        $validator->after(function (IlluminateValidator $validator): void {
            $customerId = (int) $this->input('customer_id');
            if ($customerId < 1) {
                return;
            }

            $customer = User::query()->find($customerId);
            if ($customer === null || ! $customer->isActiveCustomer()) {
                $validator->errors()->add('customer_id', 'customer_id must be an active customer account.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->writeAudit('load_failed', $validator->errors()->toArray());

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'code' => 'VALIDATION_ERROR',
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        $this->writeAudit('load_unauthorized', ['reason' => 'form_request']);

        throw new HttpResponseException(response()->json([
            'message' => 'You are not allowed to load AST wallets.',
            'code' => 'FORBIDDEN',
        ], 403));
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function writeAudit(string $action, array $details): void
    {
        WalletAuditLog::query()->create([
            'wallet_id' => null,
            'actor_id' => $this->user()?->id,
            'actor_type' => WalletAuditLog::ACTOR_ADMIN,
            'action' => $action,
            'old_value' => null,
            'new_value' => $details,
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ]);
    }
}
