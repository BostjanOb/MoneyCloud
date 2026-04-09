<?php

namespace App\Enums;

enum InvestmentProviderSlug: string
{
    case IBKR = 'ibkr';
    case ILIRIKA = 'ilirika';

    public function label(): string
    {
        return match ($this) {
            self::IBKR => 'IBKR',
            self::ILIRIKA => 'Ilirika',
        };
    }
}
