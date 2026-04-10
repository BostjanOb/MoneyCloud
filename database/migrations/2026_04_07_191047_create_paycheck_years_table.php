<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paycheck_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained();
            $table->smallInteger('year');
            $table->tinyInteger('child1_months')->default(0);
            $table->tinyInteger('child2_months')->default(0);
            $table->tinyInteger('child3_months')->default(0);
            $table->timestamps();

            $table->unique(['person_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paycheck_years');
    }
};
