<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Beard;
use App\Enums\HairColor;
use App\Enums\HairStyle;
use App\Enums\SkinTone;
use App\Enums\TaskDifficulty;
use App\Enums\TaskType;
use App\Models\Character;
use App\Models\Item;
use App\Models\Task;
use App\Models\User;
use App\Services\EquipmentService;
use App\Services\RewardService;
use App\Services\ShopService;
use App\Services\StarterKit;
use App\Services\UserCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// A hero with history for reviewers: level 4, a 12-day streak, bought and worn items
final class DemoSeeder extends Seeder
{
    public const string EMAIL = 'demo@example.com';

    public const string PASSWORD = 'demo-password';

    // Where an account with a known password is acceptable
    private const array ENVIRONMENTS = ['local', 'testing'];

    private const string TIMEZONE = 'Europe/Moscow';

    private const string HERO_NAME = 'Демо';

    // The history completes them in this order: a day with N completions has the first N done
    private const array DAILIES = ['30 минут Go', 'Зарядка', 'Прочитать 20 страниц', 'Английский 15 минут', 'Разобрать почту'];

    // Dailies stay medium: their history was rewarded so. Todos show the other difficulties
    private const array TODOS = [
        'Записаться к стоматологу' => TaskDifficulty::Easy,
        'Купить кроссовки для бега' => TaskDifficulty::Medium,
        'Настроить бэкап фотографий' => TaskDifficulty::Hard,
    ];

    // Completions on each day of a 12-day streak that ended yesterday, oldest first.
    // On the tuned balance this is 614 XP, 36 short of level 5, so two completions today level up.
    // 39 of the 60 dailies due are done: the stats page shows 65%
    private const array COMPLETIONS_PER_DAY = [3, 3, 3, 3, 3, 3, 3, 3, 3, 4, 4, 4];

    // The tasks exist from the morning of the first day of the history, so all of it counts in the stats
    private const int CREATED_AT_HOUR = 9;

    private const int COMPLETED_AT_HOUR = 19;

    private const array PURCHASES = ['green_bandana', 'hunter_vest', 'round_shield', 'hunting_bow', 'leather_armor'];

    // Bought but not worn items stay in the inventory: the bow and the round shield would hide the face at 16 px
    private const array WORN = ['green_bandana', 'leather_armor', 'leather_pants', 'wooden_sword', 'wooden_buckler'];

    public function __construct(
        private readonly StarterKit $starterKit,
        private readonly UserCalendar $calendar,
        private readonly RewardService $rewards,
        private readonly ShopService $shop,
        private readonly EquipmentService $equipment,
    ) {}

    /**
     * Seed the demo account.
     */
    public function run(): void
    {
        // The password is published in the README, so the account must never appear on a public server
        if (! app()->environment(self::ENVIRONMENTS)) {
            $this->command?->warn('Demo user is only seeded in local development, skipped.');

            return;
        }

        // Safe on every container start: an existing demo account keeps whatever the reviewer did,
        // its history only moves forward so the streak is still alive
        $existing = User::query()->where('email', self::EMAIL)->first();

        if ($existing !== null) {
            $moved = $this->freshen($existing);
            $this->command?->info($moved === 0
                ? 'Demo user already exists and is up to date.'
                : 'Demo history moved '.$moved.' '.Str::plural('day', $moved).' forward.');

            return;
        }

        DB::transaction(function (): void {
            $user = User::query()->create([
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
                'timezone' => self::TIMEZONE,
            ]);
            /** @var Character $character */
            $character = $user->character()->create([
                'name' => self::HERO_NAME,
                // A clear face: a beard and long hair blur into the armor at this size
                'skin_tone' => SkinTone::Light,
                'hair_style' => HairStyle::Short,
                'hair_color' => HairColor::Brown,
                'beard' => Beard::None,
            ]);
            $this->starterKit->grantTo($character);

            /** @var Collection<int, Task> $dailies */
            $dailies = collect(self::DAILIES)
                ->map(fn (string $title): Task => $user->tasks()->create(['title' => $title, 'type' => TaskType::Daily]));
            foreach (self::TODOS as $title => $difficulty) {
                $user->tasks()->create(['title' => $title, 'type' => TaskType::Todo, 'difficulty' => $difficulty]);
            }

            $today = $this->calendar->today($user);
            $user->tasks()->update([
                'created_at' => $today->subDays(count(self::COMPLETIONS_PER_DAY))->setTime(self::CREATED_AT_HOUR, 0)->utc(),
            ]);
            $this->seedHistory($today, $dailies, $character);
            $this->buyAndWear($character);
            // Purchases changed a locked copy of the row, so the gold here is stale
            $this->shop->markSeen($character->refresh());
        });
    }

    /**
     * Completions of past days with the rewards the streak gave on each of them.
     *
     * @param  Collection<int, Task>  $dailies
     */
    private function seedHistory(CarbonImmutable $today, Collection $dailies, Character $character): void
    {
        $days = count(self::COMPLETIONS_PER_DAY);

        foreach (self::COMPLETIONS_PER_DAY as $index => $count) {
            $day = $today->subDays($days - $index);
            $reward = $this->rewards->rewardFor($index + 1);

            foreach ($dailies->take($count) as $task) {
                $task->completions()->create([
                    'period_date' => $day->toDateString(),
                    'completed_at' => $day->setTime(self::COMPLETED_AT_HOUR, 0),
                    'xp_awarded' => $reward->xp,
                    'gold_awarded' => $reward->gold,
                ]);
                $this->rewards->grant($character, $reward);
            }
        }
    }

    // Moves the whole history by whole days so its last active day is yesterday. Every row moves by the same
    // number of days, so the streak stays as it was. Returns the days moved
    private function freshen(User $user): int
    {
        $completions = DB::table('task_completions')
            ->join('tasks', 'tasks.id', '=', 'task_completions.task_id')
            ->where('tasks.user_id', $user->id)
            ->orderByDesc('task_completions.period_date')
            ->get(['task_completions.id', 'task_completions.period_date']);
        $last = $completions->first()?->period_date;
        $yesterday = $this->calendar->today($user)->subDay();

        if (! is_string($last) || $last >= $yesterday->toDateString()) {
            return 0;
        }

        $days = (int) CarbonImmutable::parse($last, $user->timezone)->diffInDays($yesterday);

        DB::transaction(function () use ($user, $days, $completions): void {
            // Postgres checks the unique task and day after every row, so one statement would hit a neighbour
            // not moved yet. Latest first, each row lands on a day that is already free; the demo has few rows
            foreach ($completions as $completion) {
                DB::update(
                    'update task_completions set period_date = period_date + cast(? as integer),
                        completed_at = completed_at + make_interval(days => cast(? as integer))
                        where id = ?',
                    [$days, $days, $completion->id],
                );
            }
            // A task made or an item bought after the last completion would land in the future, so stop at now
            $now = now();
            DB::update(
                'update tasks set created_at = least(created_at + make_interval(days => cast(? as integer)), ?)
                    where user_id = ?',
                [$days, $now, $user->id],
            );
            DB::update(
                'update inventory_items set acquired_at = least(acquired_at + make_interval(days => cast(? as integer)), ?)
                    where character_id in (select id from characters where user_id = ?)',
                [$days, $now, $user->id],
            );
        });

        return $days;
    }

    private function buyAndWear(Character $character): void
    {
        foreach (self::PURCHASES as $code) {
            $this->shop->buy($character, $this->item($code));
        }

        foreach (self::WORN as $code) {
            $item = $this->item($code);
            $this->equipment->equip($character, $item->slot, $item);
        }
    }

    private function item(string $code): Item
    {
        return Item::query()->where('code', $code)->sole();
    }
}
