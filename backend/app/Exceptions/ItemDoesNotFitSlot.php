<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ItemSlot;
use App\Models\Item;
use DomainException;

// A programming error: requests are validated before they reach the service
final class ItemDoesNotFitSlot extends DomainException
{
    public static function for(Item $item, ItemSlot $slot): self
    {
        return new self("Item {$item->code} is made for {$item->slot->value}, not for {$slot->value}.");
    }
}
