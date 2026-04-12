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
            $table->string('isin')->nullable()->unique();
            $table->boolean('taxable')->default(true);
            $table->string('price_source');
            $table->string('external_source_id')->nullable()->index();

            $table->decimal('current_price', 10, 2)->default(0);
            $table->timestamp('price_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['type', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_symbols');
    }
};
