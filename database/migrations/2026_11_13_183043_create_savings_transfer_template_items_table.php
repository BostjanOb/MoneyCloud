<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_transfer_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_transfer_template_id')->constrained(indexName: 'savings_tt_items_template_id_foreign')->cascadeOnDelete();
            $table->foreignId('savings_account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['savings_transfer_template_id', 'savings_account_id'], 'savings_transfer_template_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transfer_template_items');
    }
};
