<?php

namespace App\Enums;

enum BalanceSyncProvider: string
{
    case Binance = 'binance';

    public function label(): string
    {
        return match ($this) {
            self::Binance => 'Binance',
        };
    }
}
