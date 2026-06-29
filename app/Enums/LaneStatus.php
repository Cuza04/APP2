<?php

namespace App\Enums;

enum LaneStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Closed => 'Cerrado',
            self::Maintenance => 'Mantenimiento',
        };
    }
}
