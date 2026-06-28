<?php

use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use App\Services\CryptoDcaPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function makeBinanceCsv(): UploadedFile
{
    $contents = <<<'CSV'
    Create Time,Wallet,Frequency,Hourly,From Amount,From Coin,To Amount,To Coin,Price,Inverse Price,Settlement Date,Plan ID,Status
    2026-06-21 21:18:20,SPOT,WEEKLY,- -,20,EUR,0.01318169,ETH,1517.25576187,0.00065908,2026-06-21 21:18:20,1273656,SUCCESS
    2026-06-21 21:04:21,SPOT,WEEKLY,- -,30,EUR,0.00053411,BTC,56167.83216783,0.0000178,2026-06-21 21:04:22,9250107,SUCCESS
    CSV;

    $path = tempnam(sys_get_temp_dir(), 'binance').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'binance.csv', 'text/csv', null, true);
}

it('imports binance csv rows with four digit years', function () {
    $provider = InvestmentProvider::factory()->crypto()->create();
    InvestmentSymbol::factory()->crypto('ETH')->create();
    InvestmentSymbol::factory()->crypto('BTC')->create();

    $result = app(CryptoDcaPurchaseService::class)->importBinanceCsv(
        makeBinanceCsv(),
        $provider->id,
        false,
    );

    expect($result['created'])->toBe(2);

    $eth = InvestmentPurchase::query()
        ->whereHas('symbol', fn ($q) => $q->where('symbol', 'ETH'))
        ->firstOrFail();

    expect($eth->purchased_at->format('Y-m-d H:i:s'))->toBe('2026-06-21 21:18:20');
});
