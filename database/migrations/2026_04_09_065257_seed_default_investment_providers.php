<?php

use App\Enums\InvestmentProviderSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        collect([
            [
                'slug' => InvestmentProviderSlug::IBKR->value,
                'name' => InvestmentProviderSlug::IBKR->label(),
                'sort_order' => 1,
            ],
            [
                'slug' => InvestmentProviderSlug::ILIRIKA->value,
                'name' => InvestmentProviderSlug::ILIRIKA->label(),
                'sort_order' => 2,
            ],
        ])->each(function (array $provider) use ($timestamp): void {
            DB::table('investment_providers')->updateOrInsert(
                ['slug' => $provider['slug']],
                [
                    'name' => $provider['name'],
                    'sort_order' => $provider['sort_order'],
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ],
            );
        });
    }

    public function down(): void
    {
        DB::table('investment_providers')
            ->whereIn('slug', [
                InvestmentProviderSlug::IBKR->value,
                InvestmentProviderSlug::ILIRIKA->value,
            ])
            ->delete();
    }
};
