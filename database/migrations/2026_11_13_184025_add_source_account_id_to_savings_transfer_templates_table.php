<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_transfer_templates', function (Blueprint $table) {
            $table->foreignId('source_account_id')
                ->nullable()
                ->after('name')
                ->constrained('savings_accounts', indexName: 'savings_tt_source_account_id_foreign')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('savings_transfer_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_account_id');
        });
    }
};
