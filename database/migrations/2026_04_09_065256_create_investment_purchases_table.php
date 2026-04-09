<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_provider_id')
                ->constrained('investment_providers')
                ->cascadeOnDelete();
            $table->foreignId('investment_symbol_id')
                ->constrained('investment_symbols')
                ->restrictOnDelete();
            $table->dateTime('purchased_at');
            $table->decimal('quantity', 16, 8);
            $table->decimal('price_per_unit', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('yield', 10, 2)->nullable();
            $table->date('coupon_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['investment_provider_id', 'purchased_at']);
            $table->index(['investment_symbol_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_purchases');
    }
};
