<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

final class LoginController extends Controller
{
    /**
     * Войти.
     *
     * После 5 неудачных попыток вход блокируется на минуту.
     */
    public function __invoke(LoginRequest $request): UserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('character.equippedItems.item'));
    }
}
