<?php

namespace App\Enums;

enum BalanceSyncProvider: string
{
    case Binance = 'binance';

    case RevolutX = 'revolutx';

    public function label(): string
    {
        return match ($this) {
            self::Binance => 'Binance',
            self::RevolutX => 'Revolut X',
        };
    }
}
