<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RegisterController extends Controller
{
    /**
     * Зарегистрироваться и войти.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user->load('character.equippedItems.item'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
