<?php

namespace Database\Seeders;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use Illuminate\Database\Seeder;

class CryptoSymbolSeeder extends Seeder
{
    public function run(): void
    {
        collect(['BTC', 'ETH', 'USDC', 'BNB', 'ICP'])
            ->each(function (string $symbol): void {
                InvestmentSymbol::query()->updateOrCreate(
                    [
                        'type' => InvestmentSymbolType::CRYPTO->value,
                        'symbol' => $symbol,
                    ],
                    [
                        'isin' => null,
                        'taxable' => false,
                        'price_source' => 'manual',
                        'current_price' => '0.00',
                    ],
                );
            });
    }
}
