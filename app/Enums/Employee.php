<?php

namespace App\Enums;

enum Employee: string
{
    case BOSTJAN = 'bostjan';
    case JASNA = 'jasna';

    public function label(): string
    {
        return match ($this) {
            self::BOSTJAN => 'Boštjan',
            self::JASNA => 'Jasna',
        };
    }
}
