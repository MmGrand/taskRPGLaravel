<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Emails are stored lowercased; Postgres compares strings case-sensitively,
 * so every request that looks a user up by email must normalize it the same way.
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower(trim($this->string('email')->toString()))]);
        }
    }
}
