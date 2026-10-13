<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

final class StoreTaskRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
            'type' => ['required', Rule::enum(TaskType::class)],
        ];
    }
}
