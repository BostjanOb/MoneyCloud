<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year_from');
            $table->smallInteger('year_to')->nullable();
            $table->json('general_relief_brackets')->nullable();
            $table->decimal('child_relief1', 10, 2);
            $table->decimal('child_relief2', 10, 2);
            $table->decimal('child_relief3', 10, 2);
            $table->json('brackets');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
