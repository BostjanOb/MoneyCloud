<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_advisor_reports', function (Blueprint $table) {
            $table->dropColumn('provider');
            $table->string('model')->nullable()->after('generated_at');
            $table->json('usage')->nullable()->after('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_advisor_reports', function (Blueprint $table) {
            $table->dropColumn(['model', 'usage']);
            $table->string('provider')->nullable()->after('generated_at');
        });
    }
};
