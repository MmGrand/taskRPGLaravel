<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Weekdays;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Weekdays, Weekdays>
 */
final class WeekdaysCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Weekdays
    {
        return Weekdays::fromMask((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return $value instanceof Weekdays ? $value->mask : Weekdays::fromMask((int) $value)->mask;
    }
}
