<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paycheck_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paycheck_year_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->boolean('taxable')->default(false);
            $table->decimal('paid_tax', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paycheck_bonuses');
    }
};
