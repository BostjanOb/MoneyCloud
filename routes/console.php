<?php

use App\Console\Commands\CaptureMonthlyPortfolioSnapshotCommand;
use App\Console\Commands\RefreshCryptoPricesCommand;
use App\Console\Commands\RefreshLjsePricesCommand;
use App\Console\Commands\RefreshYfApiPricesCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CaptureMonthlyPortfolioSnapshotCommand::class)
    ->monthlyOn(1, '19:00')
    ->timezone('Europe/Ljubljana')
    ->withoutOverlapping();

Schedule::command(RefreshCryptoPricesCommand::class)
    ->everyThreeHours()
    ->timezone('Europe/Ljubljana')
    ->withoutOverlapping();

Schedule::command(RefreshYfApiPricesCommand::class)
    ->everyThreeHours()
    ->timezone('Europe/Ljubljana')
    ->withoutOverlapping();

Schedule::command(RefreshLjsePricesCommand::class)
    ->everyThreeHours()
    ->timezone('Europe/Ljubljana')
    ->withoutOverlapping();
