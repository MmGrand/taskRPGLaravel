<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Item;
use DomainException;

// The player may not buy this item right now; unlike ItemDoesNotFitSlot the message is meant for the player
final class PurchaseRefused extends DomainException
{
    public static function notForSale(): self
    {
        return new self('Этот предмет не продаётся: его дают за достижение.');
    }

    public static function alreadyOwned(): self
    {
        return new self('Этот предмет уже куплен.');
    }

    public static function levelTooLow(Item $item): self
    {
        return new self("Предмет откроется на уровне {$item->required_level}.");
    }

    public static function notEnoughGold(Item $item, int $gold): self
    {
        return new self("Не хватает золота: нужно {$item->price}, есть {$gold}.");
    }
}
