<?php

use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Services\CryptoPortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it returns only manual crypto balances in balance rows', function () {
    $service = app(CryptoPortfolioService::class);
    $provider = InvestmentProvider::factory()->crypto()->create([
        'name' => 'Ledger',
    ]);
    $symbol = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);

    CryptoBalance::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'manual_quantity' => '0.50000000',
        'apy' => '6.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $symbol->id,
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ]);

    $rows = $service->balanceRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['manual_quantity'])->toBe('0.50000000')
        ->and($rows[0]['current_value'])->toBe('25000.00')
        ->and($rows[0]['apy'])->toBe('6.00')
        ->and($rows[0]['annual_interest'])->toBe('1500.00')
        ->and($rows[0]['monthly_interest'])->toBe('125.00');
});

test('it groups balance totals by symbol', function () {
    $service = app(CryptoPortfolioService::class);
    $ledger = InvestmentProvider::factory()->crypto('ledger', 'Ledger')->create();
    $nexo = InvestmentProvider::factory()->crypto('nexo', 'NEXO')->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create([
        'current_price' => '3000.00',
    ]);

    CryptoBalance::factory()->create([
        'investment_provider_id' => $ledger->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '0.50000000',
    ]);
    CryptoBalance::factory()->create([
        'investment_provider_id' => $nexo->id,
        'investment_symbol_id' => $btc->id,
        'manual_quantity' => '0.25000000',
        'apy' => '6.00',
    ]);
    CryptoBalance::factory()->create([
        'investment_provider_id' => $ledger->id,
        'investment_symbol_id' => $eth->id,
        'manual_quantity' => '2.00000000',
    ]);

    $summary = $service->balanceSymbolSummary();

    expect($summary)->toHaveCount(2)
        ->and($summary[0]['symbol'])->toBe('BTC')
        ->and($summary[0]['quantity'])->toBe('0.75000000')
        ->and($summary[0]['current_value'])->toBe('37500.00')
        ->and($summary[0]['weighted_apy'])->toBe('2.00')
        ->and($summary[0]['annual_interest'])->toBe('750.00')
        ->and($summary[0]['monthly_interest'])->toBe('62.50')
        ->and($summary[0]['balances'])->toHaveCount(2)
        ->and($summary[0]['provider_count'])->toBe(2)
        ->and($summary[1]['symbol'])->toBe('ETH')
        ->and($summary[1]['quantity'])->toBe('2.00000000')
        ->and($summary[1]['current_value'])->toBe('6000.00')
        ->and($summary[1]['provider_count'])->toBe(1);
});

test('it groups dca totals by symbol', function () {
    $service = app(CryptoPortfolioService::class);
    $provider = InvestmentProvider::factory()->crypto()->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);
    $eth = InvestmentSymbol::factory()->crypto('ETH')->create([
        'current_price' => '3000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'transaction_type' => 'sell',
        'quantity' => '0.02500000',
        'price_per_unit' => '48000.00',
        'fee' => '4.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $eth->id,
        'quantity' => '2.00000000',
        'price_per_unit' => '2000.00',
        'fee' => '3.00',
    ]);

    $groups = $service->dcaSymbolGroups();

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['symbol']['symbol'])->toBe('BTC')
        ->and($groups[0]['summary']['quantity'])->toBe('0.07500000')
        ->and($groups[0]['summary']['buy_amount'])->toBe('2800.00')
        ->and($groups[0]['summary']['current_value'])->toBe('3750.00')
        ->and($groups[0]['summary']['profit_loss_amount'])->toBe('941.00')
        ->and($groups[0]['summary']['profit_loss_percentage'])->toBe('33.61')
        ->and($groups[1]['symbol']['symbol'])->toBe('ETH')
        ->and($groups[1]['summary']['quantity'])->toBe('2.00000000')
        ->and($groups[1]['summary']['buy_amount'])->toBe('4000.00')
        ->and($groups[1]['summary']['current_value'])->toBe('6000.00')
        ->and($groups[1]['summary']['profit_loss_amount'])->toBe('1997.00')
        ->and($groups[1]['summary']['profit_loss_percentage'])->toBe('49.93');
});

test('it includes per symbol purchase statistics in dca groups', function () {
    $service = app(CryptoPortfolioService::class);
    $provider = InvestmentProvider::factory()->crypto()->create();
    $btc = InvestmentSymbol::factory()->crypto('BTC')->create([
        'current_price' => '50000.00',
    ]);

    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2026-01-10 10:00:00',
        'quantity' => '0.10000000',
        'price_per_unit' => '40000.00',
        'fee' => '5.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2026-02-10 10:00:00',
        'quantity' => '0.10000000',
        'price_per_unit' => '60000.00',
        'fee' => '5.00',
    ]);
    InvestmentPurchase::factory()->create([
        'investment_provider_id' => $provider->id,
        'investment_symbol_id' => $btc->id,
        'purchased_at' => '2026-03-10 10:00:00',
        'transaction_type' => 'sell',
        'quantity' => '0.05000000',
        'price_per_unit' => '55000.00',
        'fee' => '4.00',
    ]);

    $stats = $service->dcaSymbolGroups()[0]['stats'];

    expect($stats['buy_count'])->toBe(2)
        ->and($stats['sell_count'])->toBe(1)
        ->and($stats['lowest_buy_price'])->toBe('40000.000')
        ->and($stats['highest_buy_price'])->toBe('60000.000')
        ->and($stats['average_buy_price'])->toBe('50000.000')
        ->and($stats['break_even_price'])->toBe('50050.000')
        ->and($stats['quantity_bought'])->toBe('0.20000000')
        ->and($stats['quantity_sold'])->toBe('0.05000000')
        ->and($stats['total_fees'])->toBe('14.00')
        ->and($stats['first_purchase_at'])->toStartWith('2026-01-10')
        ->and($stats['last_purchase_at'])->toStartWith('2026-03-10');
});
