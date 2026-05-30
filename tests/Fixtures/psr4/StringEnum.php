<?php declare(strict_types=1);

namespace App\Enum;

enum StringEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
