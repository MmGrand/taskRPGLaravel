<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\HabitDirection;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class StoreHabitMarkRequest extends FormRequest
{
    // Ownership is checked before the rules, so a 422 never tells that someone else's task id exists
    public function authorize(): bool
    {
        /** @var Task $task */
        $task = $this->route('task');
        abort_unless($task->user()->is($this->user()), Response::HTTP_NOT_FOUND);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(HabitDirection::class)],
        ];
    }

    public function direction(): HabitDirection
    {
        return HabitDirection::from($this->string('direction')->toString());
    }
}
