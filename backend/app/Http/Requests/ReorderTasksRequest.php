<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

final class ReorderTasksRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.Config::integer('tasks.max_active_per_user')],
            // A task deleted in another tab fails here, and the client reloads the list.
            // Someone else's task fails the same way as a missing one, so its id does not leak
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('tasks', 'id')->where('user_id', $user->id)->whereNull('deleted_at'),
            ],
        ];
    }
}
