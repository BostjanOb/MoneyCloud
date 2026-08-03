<?php

use App\Console\Commands\CaptureMonthlyPortfolioSnapshotCommand;
use App\Console\Commands\RefreshCryptoPricesCommand;
use App\Console\Commands\RefreshLjsePricesCommand;
use App\Console\Commands\RefreshYfApiPricesCommand;
use App\Console\Commands\SyncCryptoBalancesCommand;
use App\Console\Commands\SyncCryptoPurchasesCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CaptureMonthlyPortfolioSnapshotCommand::class)
    ->monthlyOn(1, '19:00')
    ->withoutOverlapping();

Schedule::command(RefreshCryptoPricesCommand::class)
    ->everyThreeHours()
    ->withoutOverlapping();

Schedule::command(RefreshYfApiPricesCommand::class)
    ->everyThreeHours()
    ->withoutOverlapping();

Schedule::command(RefreshLjsePricesCommand::class)
    ->everyThreeHours()
    ->withoutOverlapping();

Schedule::command(SyncCryptoBalancesCommand::class)
    ->everySixHours()
    ->withoutOverlapping();

Schedule::command(SyncCryptoPurchasesCommand::class)
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping();
