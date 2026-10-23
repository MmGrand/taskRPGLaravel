<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AchievementResource;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AchievementController extends Controller
{
    public function __construct(private readonly AchievementService $achievements) {}

    /**
     * Все достижения с прогрессом.
     *
     * Перед ответом выдаёт уже заслуженные достижения, поэтому история до их появления засчитывается сама.
     */
    // Earns what is due before listing. A read that writes, on purpose: awarding is idempotent, and so
    // the history made before achievements existed counts without a data migration
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->achievements->award($user);

        return response()->json([
            'data' => AchievementResource::collection($this->achievements->progressFor($user))->resolve($request),
        ]);
    }
}
