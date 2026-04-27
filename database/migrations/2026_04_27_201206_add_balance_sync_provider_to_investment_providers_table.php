<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_providers', function (Blueprint $table) {
            $table->string('balance_sync_provider')
                ->nullable()
                ->after('supported_symbol_types');
        });
    }

    public function down(): void
    {
        Schema::table('investment_providers', function (Blueprint $table) {
            $table->dropColumn('balance_sync_provider');
        });
    }
};
