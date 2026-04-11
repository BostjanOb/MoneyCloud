<?php

namespace App\Services;

use App\Contracts\InvestmentPriceRefreshService;

class NullInvestmentPriceRefreshService implements InvestmentPriceRefreshService
{
    public function refresh(): array
    {
        return [
            'updated_count' => 0,
            'skipped_count' => 0,
            'failed_symbols' => [],
        ];
    }
}
