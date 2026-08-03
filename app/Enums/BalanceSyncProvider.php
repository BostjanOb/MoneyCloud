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

    /**
     * Whether the exchange exposes a trade history the app can import from.
     */
    public function supportsPurchaseSync(): bool
    {
        return match ($this) {
            self::Binance => false,
            self::RevolutX => true,
        };
    }
}
