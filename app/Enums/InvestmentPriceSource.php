<?php

namespace App\Enums;

enum InvestmentPriceSource: string
{
    case MANUAL = 'manual';
    case COINMARKETCAP = 'coinmarketcap';
    case YFAPI = 'yfapi';
    case LJSE = 'ljse';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Ročno',
            self::COINMARKETCAP => 'CoinMarketCap',
            self::YFAPI => 'YF API',
            self::LJSE => 'LJSE',
        };
    }

    public function supportsType(InvestmentSymbolType $type): bool
    {
        return match ($this) {
            self::MANUAL => true,
            self::COINMARKETCAP => $type === InvestmentSymbolType::CRYPTO,
            self::YFAPI => in_array($type, [InvestmentSymbolType::STOCK, InvestmentSymbolType::ETF], true),
            self::LJSE => in_array($type, [InvestmentSymbolType::STOCK, InvestmentSymbolType::BOND], true),
        };
    }
}
