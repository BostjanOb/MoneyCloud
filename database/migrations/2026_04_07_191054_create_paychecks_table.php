<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paychecks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paycheck_year_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('month');
            $table->decimal('net', 10, 2)->nullable();
            $table->decimal('gross', 10, 2);
            $table->decimal('contributions', 10, 2);
            $table->decimal('taxes', 10, 2);
            $table->timestamps();

            $table->unique(['paycheck_year_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paychecks');
    }
};
