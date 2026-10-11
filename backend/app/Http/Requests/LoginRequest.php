<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class LoginRequest extends FormRequest
{
    use NormalizesEmail;

    private const int SECONDS_PER_MINUTE = 60;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials on the session guard.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), Config::integer('auth.throttle.login_decay_seconds'));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), Config::integer('auth.throttle.login_max_attempts'))) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / self::SECONDS_PER_MINUTE),
            ]),
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
