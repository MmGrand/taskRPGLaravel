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
    // Leaves the hero 26 XP short of level 5, so two completions today level up
    private const array COMPLETIONS_PER_DAY = [4, 4, 4, 5, 4, 4, 5, 5, 4, 5, 5, 4];

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

        // Safe on every container start: an existing demo account, with whatever the reviewer did, stays
        if (User::query()->where('email', self::EMAIL)->exists()) {
            $this->command?->info('Demo user already exists, skipped.');

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

            $this->seedHistory($this->calendar->today($user), $dailies, $character);
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
