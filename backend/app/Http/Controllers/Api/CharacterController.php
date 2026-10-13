<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCharacter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Resources\CharacterResource;
use App\Models\Character;
use App\Models\User;
use App\Services\StarterKit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CharacterController extends Controller
{
    use ResolvesCharacter;

    private const string ALREADY_EXISTS = 'Character already exists.';

    public function __construct(private readonly StarterKit $starterKit) {}

    public function show(Request $request): CharacterResource
    {
        return new CharacterResource($this->characterOf($request)->load('equippedItems.item'));
    }

    public function store(StoreCharacterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->character()->exists(), Response::HTTP_CONFLICT, self::ALREADY_EXISTS);

        try {
            $character = DB::transaction(function () use ($user, $request): Character {
                /** @var Character $character */
                $character = $user->character()->create($request->validated());
                $this->starterKit->grantTo($character);

                return $character;
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel request created the character between the check and the insert
            abort(Response::HTTP_CONFLICT, self::ALREADY_EXISTS);
        }

        return CharacterResource::make($character->load('equippedItems.item'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
