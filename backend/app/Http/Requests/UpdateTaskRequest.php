<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskDifficulty;
use App\Enums\TaskType;
use App\Http\Requests\Concerns\BuildsTaskAttributes;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

// Every field is optional; the type of a task never changes
final class UpdateTaskRequest extends FormRequest
{
    use BuildsTaskAttributes;

    // Ownership is checked before the rules: they read the type of the task, which must not leak.
    // Someone else's task answers 404, like everywhere else, so its id does not leak either
    public function authorize(): bool
    {
        abort_unless($this->task()->user()->is($this->user()), Response::HTTP_NOT_FOUND);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
            'difficulty' => ['sometimes', Rule::enum(TaskDifficulty::class)],
            // A todo has no schedule
            'weekdays' => ['sometimes', Rule::prohibitedIf(fn (): bool => $this->task()->type === TaskType::Todo), 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }

    // The route model is bound before the request is validated
    private function task(): Task
    {
        /** @var Task */
        return $this->route('task');
    }
}
