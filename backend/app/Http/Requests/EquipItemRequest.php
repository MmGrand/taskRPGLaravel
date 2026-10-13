<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ItemSlot;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EquipItemRequest extends FormRequest
{
    // Without a character there is nothing to equip: answer like GET /api/character
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        return $user->character !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        /** @var Character $character */
        $character = $user->character;
        /** @var ItemSlot $slot */
        $slot = $this->route('slot');

        return [
            'item_id' => [
                'required',
                'integer',
                // Owned by this character
                Rule::exists('inventory_items', 'item_id')->where('character_id', $character->id),
                // And made for the requested slot
                Rule::exists('items', 'id')->where('slot', $slot->value),
            ],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException('Character has not been created yet.');
    }
}
