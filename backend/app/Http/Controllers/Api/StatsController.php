<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    // The completion map of the last weeks, the share of dailies done and the marks of each habit; read only
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $rate = $this->stats->dailyCompletionRate($user);

        return response()->json(['data' => [
            'heatmap' => $this->stats->heatmap($user),
            'dailies' => [
                'days' => $rate->days,
                'scheduled' => $rate->scheduled,
                'completed' => $rate->completed,
                'percent' => $rate->percent(),
            ],
            'habits' => $this->stats->habits($user),
        ]]);
    }
}
