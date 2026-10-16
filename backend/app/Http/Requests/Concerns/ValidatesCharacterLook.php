<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\Beard;
use App\Enums\HairColor;
use App\Enums\HairStyle;
use App\Enums\SkinTone;
use Illuminate\Validation\Rule;

// Name and look of a hero: the same rules when creating it and when changing it in the profile
trait ValidatesCharacterLook
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[\pL\pN _-]+$/u'],
            'skin_tone' => ['required', Rule::enum(SkinTone::class)],
            'hair_style' => ['required', Rule::enum(HairStyle::class)],
            'hair_color' => ['required', Rule::enum(HairColor::class)],
            'beard' => ['required', Rule::enum(Beard::class)],
        ];
    }
}
