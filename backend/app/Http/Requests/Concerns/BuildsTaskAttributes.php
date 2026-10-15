<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\ValueObjects\Weekdays;

trait BuildsTaskAttributes
{
    /**
     * Validated fields ready for the model: weekdays arrive as ISO day numbers.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $this->validated();

        if (isset($attributes['weekdays']) && is_array($attributes['weekdays'])) {
            $attributes['weekdays'] = Weekdays::fromIsoDays(array_values(array_map(intval(...), $attributes['weekdays'])));
        }

        return $attributes;
    }
}
