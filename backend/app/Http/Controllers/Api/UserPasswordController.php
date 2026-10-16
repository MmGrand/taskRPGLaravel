<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class UserPasswordController extends Controller
{
    private const int REMEMBER_TOKEN_LENGTH = 60;

    // Other devices keep the old password hash in their sessions, so Sanctum's AuthenticateSession
    // logs them out on their next request; this session gets the new hash when the response leaves
    public function __invoke(UpdatePasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'password' => $request->string('password')->toString(),
            // "Remember me" cookies of other devices must not sign them back in
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
        ])->save();
        $request->session()->regenerate();

        return response()->noContent();
    }
}
