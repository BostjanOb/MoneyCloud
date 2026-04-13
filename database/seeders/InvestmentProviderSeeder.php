<?php

namespace Database\Seeders;

use App\Models\InvestmentProvider;
use Illuminate\Database\Seeder;

class InvestmentProviderSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            [
                'slug' => 'ibkr',
                'name' => 'IBKR',
                'sort_order' => 1,
                'requires_linked_savings_account' => true,
                'supported_symbol_types' => ['etf', 'stock', 'crypto'],
            ],
            [
                'slug' => 'ilirika',
                'name' => 'Ilirika',
                'sort_order' => 2,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => ['bond'],
            ],
            [
                'slug' => 'nexo',
                'name' => 'NEXO',
                'sort_order' => 10,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => ['crypto'],
            ],
            [
                'slug' => 'ledger',
                'name' => 'Ledger',
                'sort_order' => 11,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => ['crypto'],
            ],
            [
                'slug' => 'binance',
                'name' => 'Binance',
                'sort_order' => 12,
                'requires_linked_savings_account' => false,
                'supported_symbol_types' => ['crypto'],
            ],
        ])->each(function (array $provider): void {
            InvestmentProvider::query()->updateOrCreate(
                ['slug' => $provider['slug']],
                [
                    'name' => $provider['name'],
                    'sort_order' => $provider['sort_order'],
                    'requires_linked_savings_account' => $provider['requires_linked_savings_account'],
                    'supported_symbol_types' => $provider['supported_symbol_types'],
                ],
            );
        });
    }
}