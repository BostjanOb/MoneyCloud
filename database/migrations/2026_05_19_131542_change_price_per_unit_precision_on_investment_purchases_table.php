<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table) {
            $table->decimal('price_per_unit', 10, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table) {
            $table->decimal('price_per_unit', 10, 2)->change();
        });
    }
};
