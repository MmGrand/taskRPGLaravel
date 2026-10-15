<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskDifficulty;
use App\Models\Item;
use App\Services\BalanceSimulator;
use App\ValueObjects\Simulation;
use App\ValueObjects\SimulationEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('balance:simulate
    {--tasks=5 : Tasks completed every day}
    {--days=90 : Days to play}
    {--difficulty=medium : easy, medium or hard}')]
#[Description('Play the progression on the real balance and show when each level and item comes')]
final class SimulateBalance extends Command
{
    // Ten years of play: enough for any balance question, and a typo cannot hang the command
    private const int MAX_DAYS = 3650;

    public function handle(BalanceSimulator $simulator): int
    {
        $tasks = filter_var($this->option('tasks'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_DAYS]]);
        $difficulty = TaskDifficulty::tryFrom((string) $this->option('difficulty'));

        if ($tasks === false || $days === false) {
            $this->error('--tasks and --days must be whole numbers of at least 1, --days at most '.self::MAX_DAYS.'.');

            return self::FAILURE;
        }

        if ($difficulty === null) {
            $this->error('--difficulty must be easy, medium or hard.');

            return self::FAILURE;
        }

        $catalog = Item::query()->orderBy('required_level')->orderBy('id')->get();

        if ($catalog->isEmpty()) {
            $this->error('The item catalog is empty. Run the ItemCatalogSeeder first.');

            return self::FAILURE;
        }

        $simulation = $simulator->simulate($tasks, $days, $difficulty, $catalog);

        $this->table(['Day', 'Event'], array_map(
            fn (SimulationEvent $event): array => [$event->day, $event->text],
            $simulation->events,
        ));
        $this->summarize($simulation, $catalog->max('required_level'), $days);

        return self::SUCCESS;
    }

    private function summarize(Simulation $simulation, int $topLevel, int $days): void
    {
        foreach ($simulation->levelDays as $level => $day) {
            if ($level === 5 || $level === $topLevel) {
                $this->line("Level {$level} on day {$day}.");
            }
        }

        $this->line($simulation->catalogDay === null
            ? "Catalog not finished in {$days} days, level {$simulation->finalLevel} reached."
            : "Whole catalog bought by day {$simulation->catalogDay}.");
    }
}
