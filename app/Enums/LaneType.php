<?php

namespace App\Enums;

enum LaneType: string
{
    case FixedEntry = 'fixed_entry';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::FixedEntry => 'Entrada fija',
            self::Flexible => 'Flexible',
        };
    }
}
