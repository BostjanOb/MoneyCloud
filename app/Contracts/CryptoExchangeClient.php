<?php

namespace App\Contracts;

interface CryptoExchangeClient
{
    /**
     * Determine whether the credentials required by the exchange are available.
     */
    public function isConfigured(): bool;

    /**
     * Total held quantity per asset, keyed by uppercase ticker.
     *
     * Assets omitted from the returned map are left untouched during a sync,
     * while assets present with a zero quantity are written as zero.
     *
     * @return array<string, float>
     */
    public function getBalanceOverview(): array;
}
