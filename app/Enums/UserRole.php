<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Operator => 'Operador',
        };
    }
}
