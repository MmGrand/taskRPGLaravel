<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTimezoneRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Some browsers still report backward-compatible names such as Asia/Calcutta
            'timezone' => ['required', 'string', 'timezone:all_with_bc'],
        ];
    }
}
