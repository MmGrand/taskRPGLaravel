<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    /**
     * Текущий пользователь с героем.
     */
    public function __invoke(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('character.equippedItems.item'));
    }
}
