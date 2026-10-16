<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCharacterLook;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCharacterRequest extends FormRequest
{
    use ValidatesCharacterLook;
}
