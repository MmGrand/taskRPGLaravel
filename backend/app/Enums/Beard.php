<?php

declare(strict_types=1);

namespace App\Enums;

enum Beard: string
{
    case None = 'none';
    case Full = 'full';
    case Mustache = 'mustache';
    case Long = 'long';
    case Goatee = 'goatee';
}
