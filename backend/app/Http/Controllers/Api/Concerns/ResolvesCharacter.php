<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Character;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait ResolvesCharacter
{
    private function characterOf(Request $request): Character
    {
        /** @var User $user */
        $user = $request->user();
        $character = $user->character;

        abort_if($character === null, Response::HTTP_NOT_FOUND, 'Character has not been created yet.');

        return $character;
    }
}
