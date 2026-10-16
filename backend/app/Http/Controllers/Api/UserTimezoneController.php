<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTimezoneRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TimezoneService;

final class UserTimezoneController extends Controller
{
    public function __construct(private readonly TimezoneService $timezones) {}

    // The response is built from the saved row, so "today" in it already follows the new zone
    public function __invoke(UpdateTimezoneRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $this->timezones->change($user, $request->string('timezone')->toString());

        return new UserResource($updated->load('character.equippedItems.item'));
    }
}
