<?php

namespace App\Enums;

enum InvestmentTransactionType: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Nakup',
            self::Sell => 'Prodaja',
        };
    }

    public function multiplier(): int
    {
        return match ($this) {
            self::Buy => 1,
            self::Sell => -1,
        };
    }
}
