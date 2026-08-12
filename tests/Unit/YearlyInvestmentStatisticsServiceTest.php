<?php

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Services\YearlyInvestmentStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it groups yearly invested totals by symbol and excludes fees', function () {
    $currentYear = now()->year;
    $service = new YearlyInvestmentStatisticsService;
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2024-05-10 09:00:00',
        'quantity' => '0.50000000',
        'price_per_unit' => '1000.00',
        'fee' => '20.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2024-06-10 09:00:00',
        'quantity' => '10.00000000',
        'price_per_unit' => '500.00',
        'fee' => '25.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2025-02-10 09:00:00',
        'quantity' => '0.25000000',
        'price_per_unit' => '2000.00',
        'fee' => '30.00',
    ]);

    $data = $service->pageData();

    $rowsByYear = collect($data['rows'])->keyBy('year');

    expect($data['years'])->toBe(range(2024, $currentYear))
        ->and($rowsByYear[2024]['total_amount'])->toBe('5500.00')
        ->and($rowsByYear[2025]['total_amount'])->toBe('500.00')
        ->and($rowsByYear[2024]['symbols'][(string) $btc->id]['amount'])->toBe('500.00')
        ->and($rowsByYear[2024]['symbols'][(string) $btc->id]['quantity'])->toBe('0.50000000')
        ->and($rowsByYear[2024]['symbols'][(string) $vwce->id]['amount'])->toBe('5000.00')
        ->and($data['totals']['symbols'][(string) $btc->id]['amount'])->toBe('1000.00')
        ->and($data['totals']['symbols'][(string) $btc->id]['quantity'])->toBe('0.75000000')
        ->and($data['totals']['grand_total_amount'])->toBe('6000.00');
});

test('it ignores sell transactions in yearly invested totals', function () {
    $service = new YearlyInvestmentStatisticsService;
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();

    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2024-05-10 09:00:00',
        'quantity' => '0.50000000',
        'price_per_unit' => '1000.00',
        'fee' => '20.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2024-06-10 09:00:00',
        'transaction_type' => 'sell',
        'quantity' => '0.20000000',
        'price_per_unit' => '1500.00',
        'fee' => '10.00',
    ]);

    $data = $service->pageData();
    $rowsByYear = collect($data['rows'])->keyBy('year');

    expect($rowsByYear[2024]['symbols'][(string) $btc->id]['amount'])->toBe('500.00')
        ->and($rowsByYear[2024]['symbols'][(string) $btc->id]['quantity'])->toBe('0.50000000')
        ->and($rowsByYear[2024]['total_amount'])->toBe('500.00')
        ->and($data['totals']['grand_total_amount'])->toBe('500.00');
});

test('it groups yearly invested totals by asset type', function () {
    $service = new YearlyInvestmentStatisticsService;
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create();
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create();
    $vwce = InvestmentSymbol::factory()->create([
        'type' => InvestmentSymbolType::ETF,
        'symbol' => 'VWCE',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2024-05-10 09:00:00',
        'quantity' => '0.50000000',
        'price_per_unit' => '1000.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $eth->id,
        'purchased_at' => '2024-07-10 09:00:00',
        'quantity' => '2.00000000',
        'price_per_unit' => '150.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $eth->id,
        'purchased_at' => '2024-08-10 09:00:00',
        'transaction_type' => 'sell',
        'quantity' => '1.00000000',
        'price_per_unit' => '190.73',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_symbol_id' => $vwce->id,
        'purchased_at' => '2025-03-12 09:00:00',
        'quantity' => '10.00000000',
        'price_per_unit' => '500.00',
    ]);

    $data = $service->pageData();
    $rowsByYear = collect($data['rows'])->keyBy('year');

    expect($data['types'])->toBe([
        ['value' => 'etf', 'label' => 'ETF'],
        ['value' => 'crypto', 'label' => 'Kripto'],
    ])
        ->and($rowsByYear[2024]['types']['crypto'])->toBe('800.00')
        ->and($rowsByYear[2024]['types']['etf'])->toBe('0.00')
        ->and($rowsByYear[2025]['types']['etf'])->toBe('5000.00')
        ->and($rowsByYear[2025]['types']['crypto'])->toBe('0.00')
        ->and($data['totals']['types']['crypto'])->toBe('800.00')
        ->and($data['totals']['types']['etf'])->toBe('5000.00');
});
