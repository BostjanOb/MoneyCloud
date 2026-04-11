<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('month_date')->unique();
            $table->decimal('savings_amount', 10, 2);
            $table->decimal('bond_amount', 10, 2);
            $table->decimal('etf_amount', 10, 2);
            $table->decimal('crypto_amount', 10, 2);
            $table->decimal('stock_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_portfolio_snapshots');
    }
};
