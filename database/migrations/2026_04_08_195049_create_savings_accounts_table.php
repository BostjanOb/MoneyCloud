<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('savings_accounts')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained();
            $table->string('name');
            $table->string('owner');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('apy', 5, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_accounts');
    }
};
