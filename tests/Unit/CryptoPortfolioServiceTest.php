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
    $service = new CryptoPortfolioService;
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
        ->and($rows[0]['current_value'])->toBe('25000.00');
});

test('it groups balance totals by symbol', function () {
    $service = new CryptoPortfolioService;
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
        ->and($summary[0]['provider_count'])->toBe(2)
        ->and($summary[1]['symbol'])->toBe('ETH')
        ->and($summary[1]['quantity'])->toBe('2.00000000')
        ->and($summary[1]['current_value'])->toBe('6000.00')
        ->and($summary[1]['provider_count'])->toBe(1);
});

test('it groups dca totals by symbol', function () {
    $service = new CryptoPortfolioService;
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
