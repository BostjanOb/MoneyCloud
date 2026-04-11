<?php

use App\Console\Commands\CaptureMonthlyPortfolioSnapshotCommand;
use App\Console\Commands\RefreshCryptoPricesCommand;
use App\Console\Commands\RefreshYfApiPricesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
