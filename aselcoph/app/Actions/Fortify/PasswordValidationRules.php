<?php

namespace App\Actions\Fortify;

use App\Services\Access\AccessService;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        $min = (int) (app(AccessService::class)->setting('password_min_length', config('access.password_min_length', 8)));
        $rule = Password::min($min);
        if (app(AccessService::class)->setting('password_require_mixed', config('access.password_require_mixed', true))) {
            $rule = $rule->mixedCase()->numbers();
        }

        return ['required', 'string', $rule, 'confirmed'];
    }
}
