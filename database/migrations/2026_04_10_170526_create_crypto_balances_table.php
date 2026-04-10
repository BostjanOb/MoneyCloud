<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crypto_balances')) {
            Schema::table('crypto_balances', function (Blueprint $table) {
                $table->unique(['investment_provider_id', 'investment_symbol_id'], 'cbal_provider_symbol_unique');
                $table->index(['investment_symbol_id', 'investment_provider_id'], 'cbal_symbol_provider_idx');
            });

            return;
        }

        Schema::create('crypto_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_provider_id')
                ->constrained('investment_providers')
                ->cascadeOnDelete();
            $table->foreignId('investment_symbol_id')
                ->constrained('investment_symbols')
                ->restrictOnDelete();
            $table->decimal('manual_quantity', 16, 8)->default(0);
            $table->timestamps();

            $table->unique(['investment_provider_id', 'investment_symbol_id'], 'cbal_provider_symbol_unique');
            $table->index(['investment_symbol_id', 'investment_provider_id'], 'cbal_symbol_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_balances');
    }
};
