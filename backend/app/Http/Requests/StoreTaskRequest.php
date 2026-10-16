<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskDifficulty;
use App\Enums\TaskType;
use App\Http\Requests\Concerns\BuildsTaskAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

final class StoreTaskRequest extends FormRequest
{
    use BuildsTaskAttributes;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
            'type' => ['required', Rule::enum(TaskType::class)],
            'difficulty' => ['sometimes', Rule::enum(TaskDifficulty::class)],
            // Only a daily has a schedule; left out, it is due every day
            'weekdays' => ['sometimes', 'prohibited_unless:type,'.TaskType::Daily->value, 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }
}
