<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Resources\CharacterResource;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CharacterController extends Controller
{
    private const string ALREADY_EXISTS = 'Character already exists.';

    public function show(Request $request): CharacterResource
    {
        /** @var User $user */
        $user = $request->user();
        $character = $user->character;

        abort_if($character === null, Response::HTTP_NOT_FOUND, 'Character has not been created yet.');

        return new CharacterResource($character);
    }

    public function store(StoreCharacterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->character()->exists(), Response::HTTP_CONFLICT, self::ALREADY_EXISTS);

        try {
            /** @var Character $character */
            $character = $user->character()->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            // A parallel request created the character between the check and the insert
            abort(Response::HTTP_CONFLICT, self::ALREADY_EXISTS);
        }

        return CharacterResource::make($character)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
