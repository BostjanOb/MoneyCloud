<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('symbol');
            $table->string('isin')->nullable();
            $table->boolean('taxable')->default(true);
            $table->string('price_source');
            $table->unsignedBigInteger('coinmarketcap_id')->nullable();
            $table->string('yfapi_symbol')->nullable();
            $table->decimal('current_price', 10, 2)->default(0);
            $table->timestamp('price_synced_at')->nullable();

            $table->timestamps();

            $table->index('yfapi_symbol');
            $table->index('coinmarketcap_id');
            $table->unique(['type', 'symbol']);
            $table->unique(['isin']);
            $table->index(['type', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_symbols');
    }
};
