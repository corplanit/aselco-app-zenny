<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->to(static::destination($request));
    }

    public static function destination(Request $request): string
    {
        $user = $request->user();
        $home = $user instanceof User ? $user->loginHomePath() : '/u/dashboard';
        $intended = $request->session()->pull('url.intended', $home);
        $path = parse_url((string) $intended, PHP_URL_PATH) ?: '';

        if (in_array($path, ['/dashboard', '/u/dashboard', '/login', '/'], true)) {
            return $home;
        }

        return $intended;
    }
}
