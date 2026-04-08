<?php

namespace App\Enums;

enum BonusType: string
{
    case REGRES = 'regres';
    case SP = 'sp';
    case BONI_MALICA = 'boni_malica';
    case OSTALO = 'ostalo';

    public function label(): string
    {
        return match ($this) {
            self::REGRES => 'Regres',
            self::SP => 's.p.',
            self::BONI_MALICA => 'boni malica',
            self::OSTALO => 'Ostalo',
        };
    }
}
