<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('investment_symbol_id');
            $table->unique(['investment_provider_id', 'external_id'], 'ipur_provider_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table): void {
            $table->dropUnique('ipur_provider_external_unique');
            $table->dropColumn('external_id');
        });
    }
};
