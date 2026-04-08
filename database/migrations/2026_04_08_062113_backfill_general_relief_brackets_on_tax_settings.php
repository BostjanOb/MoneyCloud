<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tax_settings')
            ->select(['id', 'general_relief'])
            ->orderBy('id')
            ->eachById(function (object $setting): void {
                DB::table('tax_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'general_relief_brackets' => json_encode([
                            [
                                'income_from' => 0,
                                'income_to' => null,
                                'base_relief' => (float) $setting->general_relief,
                                'formula_constant' => null,
                                'formula_multiplier' => null,
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tax_settings')
            ->select(['id', 'general_relief_brackets'])
            ->orderBy('id')
            ->eachById(function (object $setting): void {
                $generalReliefBrackets = json_decode($setting->general_relief_brackets ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                $firstBracket = $generalReliefBrackets[0] ?? [];

                DB::table('tax_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'general_relief' => (float) ($firstBracket['base_relief'] ?? 0),
                    ]);
            });
    }
};
