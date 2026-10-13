<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuyItemRequest extends FormRequest
{
    // Without a character there is nobody to buy for: answer like GET /api/character
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        return $user->character !== null;
    }

    /**
     * Level, gold and ownership depend on locked state, so ShopService checks them.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException('Character has not been created yet.');
    }
}
