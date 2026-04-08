<?php

use App\Http\Controllers\BonusController;
use App\Http\Controllers\PaycheckController;
use App\Http\Controllers\PaycheckYearController;
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

    Route::get('place/nastavitve', [TaxSettingController::class, 'index'])->name('place.nastavitve');
    Route::get('place/nastavitve/novo', [TaxSettingController::class, 'create'])->name('place.nastavitve.create');
    Route::get('place/nastavitve/{taxSetting}/uredi', [TaxSettingController::class, 'edit'])->name('place.nastavitve.edit');
    Route::post('place/nastavitve', [TaxSettingController::class, 'store'])->name('place.nastavitve.store');
    Route::put('place/nastavitve/{taxSetting}', [TaxSettingController::class, 'update'])->name('place.nastavitve.update');
    Route::delete('place/nastavitve/{taxSetting}', [TaxSettingController::class, 'destroy'])->name('place.nastavitve.destroy');

    Route::get('place/{employee}', [PaycheckController::class, 'index'])->name('place.index');
});

require __DIR__.'/settings.php';
