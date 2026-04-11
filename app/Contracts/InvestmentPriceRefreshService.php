<?php

namespace App\Contracts;

interface InvestmentPriceRefreshService
{
    /**
     * @return array{
     *     updated_count: int,
     *     skipped_count: int,
     *     failed_symbols: list<string>
     * }
     */
    public function refresh(): array;
}
