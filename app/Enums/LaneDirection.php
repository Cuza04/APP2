<?php

namespace App\Enums;

enum LaneDirection: string
{
    case Entry = 'entry';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Salida',
        };
    }
}
