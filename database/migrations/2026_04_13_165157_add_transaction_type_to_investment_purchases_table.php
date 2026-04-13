<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table) {
            $table->string('transaction_type')
                ->default('buy')
                ->after('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
};
