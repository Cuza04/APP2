<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completada',
            self::Superseded => 'Reemplazada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Pending;
    }
}
