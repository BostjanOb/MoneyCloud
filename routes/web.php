<?php

use App\Http\Controllers\BonusController;
use App\Http\Controllers\InvestmentProviderController;
use App\Http\Controllers\InvestmentPurchaseController;
use App\Http\Controllers\InvestmentSymbolController;
use App\Http\Controllers\PaycheckController;
use App\Http\Controllers\PaycheckYearController;
use App\Http\Controllers\SavingsAccountController;
use App\Http\Controllers\SavingsInterestController;
use App\Http\Controllers\TaxSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/', 'Dashboard')->name('dashboard');
    Route::redirect('dashboard', '/');

    Route::post('place/paycheck', [PaycheckController::class, 'store'])->name('place.paycheck.store');
    Route::put('place/paycheck/{paycheck}', [PaycheckController::class, 'update'])->name('place.paycheck.update');
    Route::delete('place/paycheck/{paycheck}', [PaycheckController::class, 'destroy'])->name('place.paycheck.destroy');

    Route::post('place/year', [PaycheckYearController::class, 'store'])->name('place.year.store');
    Route::put('place/year/{paycheckYear}', [PaycheckYearController::class, 'update'])->name('place.year.update');

    Route::post('place/bonus', [BonusController::class, 'store'])->name('place.bonus.store');
    Route::put('place/bonus/{bonus}', [BonusController::class, 'update'])->name('place.bonus.update');
    Route::delete('place/bonus/{bonus}', [BonusController::class, 'destroy'])->name('place.bonus.destroy');

    Route::prefix('varcevanje')->group(function () {
        Route::get('/', [SavingsAccountController::class, 'index'])->name('savings.index');
        Route::post('/', [SavingsAccountController::class, 'store'])->name('savings.store');
        Route::put('/{savingsAccount}', [SavingsAccountController::class, 'update'])->name('savings.update');
        Route::delete('/{savingsAccount}', [SavingsAccountController::class, 'destroy'])->name('savings.destroy');
        Route::post('/{savingsAccount}/obresti', [SavingsInterestController::class, 'store'])->name('savings.interest.store');
    });

    Route::prefix('investicije')->group(function () {
        Route::get('simboli', [InvestmentSymbolController::class, 'index'])->name('investments.symbols.index');
        Route::get('simboli/novo', [InvestmentSymbolController::class, 'create'])->name('investments.symbols.create');
        Route::get('simboli/{investmentSymbol}/uredi', [InvestmentSymbolController::class, 'edit'])->name('investments.symbols.edit');
        Route::post('simboli', [InvestmentSymbolController::class, 'store'])->name('investments.symbols.store');
        Route::put('simboli/{investmentSymbol}', [InvestmentSymbolController::class, 'update'])->name('investments.symbols.update');
        Route::delete('simboli/{investmentSymbol}', [InvestmentSymbolController::class, 'destroy'])->name('investments.symbols.destroy');

        Route::post('{investmentProvider:slug}/nakupi', [InvestmentPurchaseController::class, 'store'])->name('investments.purchases.store');
        Route::put('{investmentProvider:slug}/nakupi/{investmentPurchase}', [InvestmentPurchaseController::class, 'update'])->name('investments.purchases.update');
        Route::delete('{investmentProvider:slug}/nakupi/{investmentPurchase}', [InvestmentPurchaseController::class, 'destroy'])->name('investments.purchases.destroy');
        Route::put('{investmentProvider:slug}', [InvestmentProviderController::class, 'update'])->name('investments.providers.update');
        Route::get('{investmentProvider:slug}', [InvestmentProviderController::class, 'show'])->name('investments.providers.show');
    });

    Route::get('place/nastavitve', [TaxSettingController::class, 'index'])->name('place.nastavitve');
    Route::get('place/nastavitve/novo', [TaxSettingController::class, 'create'])->name('place.nastavitve.create');
    Route::get('place/nastavitve/{taxSetting}/uredi', [TaxSettingController::class, 'edit'])->name('place.nastavitve.edit');
    Route::post('place/nastavitve', [TaxSettingController::class, 'store'])->name('place.nastavitve.store');
    Route::put('place/nastavitve/{taxSetting}', [TaxSettingController::class, 'update'])->name('place.nastavitve.update');
    Route::delete('place/nastavitve/{taxSetting}', [TaxSettingController::class, 'destroy'])->name('place.nastavitve.destroy');

    Route::get('place/{employee}', [PaycheckController::class, 'index'])->name('place.index');
});

require __DIR__.'/settings.php';
