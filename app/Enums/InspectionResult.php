<?php

namespace App\Enums;

enum InspectionResult: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Conditional = 'conditional';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Conditional => 'Condicional',
        };
    }
}
