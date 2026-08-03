<?php

namespace App\Contracts;

use Carbon\CarbonImmutable;

interface CryptoTradeHistoryClient
{
    /**
     * Filled trades executed in the given period, normalised for persistence.
     *
     * `quantity` is the amount actually credited (net of any fee charged in the
     * traded asset) and `fee` is always expressed in the quote currency.
     *
     * @return list<array{
     *     external_id: string,
     *     base_asset: string,
     *     quote_asset: string,
     *     side: string,
     *     executed_at: string,
     *     quantity: string,
     *     price_per_unit: string,
     *     fee: string
     * }>
     */
    public function getFilledOrders(CarbonImmutable $from, CarbonImmutable $to): array;
}
