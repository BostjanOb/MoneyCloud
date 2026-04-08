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
        Schema::create('paycheck_years', function (Blueprint $table) {
            $table->id();
            $table->string('employee');
            $table->smallInteger('year');
            $table->tinyInteger('child1_months')->default(0);
            $table->tinyInteger('child2_months')->default(0);
            $table->tinyInteger('child3_months')->default(0);
            $table->timestamps();

            $table->unique(['employee', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paycheck_years');
    }
};
