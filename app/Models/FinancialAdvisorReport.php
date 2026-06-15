<?php

namespace App\Models;

use App\Enums\AdvisorProvider;
use Database\Factories\FinancialAdvisorReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'generated_at',
    'provider',
    'report',
])]
class FinancialAdvisorReport extends Model
{
    /** @use HasFactory<FinancialAdvisorReportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'provider' => AdvisorProvider::class,
            'report' => 'array',
        ];
    }

    /** @param Builder<FinancialAdvisorReport> $query */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('generated_at')->orderByDesc('id');
    }
}
