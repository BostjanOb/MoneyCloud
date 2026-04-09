<?php

namespace App\Enums;

enum InvestmentSymbolType: string
{
    case ETF = 'etf';
    case STOCK = 'stock';
    case CRYPTO = 'crypto';
    case BOND = 'bond';

    public function label(): string
    {
        return match ($this) {
            self::ETF => 'ETF',
            self::STOCK => 'Delnica',
            self::CRYPTO => 'Kripto',
            self::BOND => 'Obveznica',
        };
    }
}
