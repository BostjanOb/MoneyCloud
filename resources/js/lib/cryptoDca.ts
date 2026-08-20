export type CryptoDcaPurchasePoint = {
    purchased_at: string;
    price_per_unit: string;
    quantity: string;
    transaction_type: 'buy' | 'sell';
    transaction_type_label: string;
    provider: { name: string };
};

export type CryptoDcaChartPoint = {
    timestamp: number;
    price: number;
    currentPrice: number;
    quantity: number;
    transactionType: 'buy' | 'sell';
    transactionTypeLabel: string;
    providerName: string;
};

export type CryptoDcaBuyPriceSplit = {
    below: number;
    total: number;
};

/**
 * Turn DCA transactions into chart points sorted from oldest to newest, with
 * the symbol's current price carried on every point for the reference line.
 */
export function buildCryptoDcaChartData(
    purchases: readonly CryptoDcaPurchasePoint[],
    currentPrice: string | number,
): CryptoDcaChartPoint[] {
    const price = Number(currentPrice);

    return purchases
        .map((purchase) => ({
            timestamp: new Date(purchase.purchased_at).getTime(),
            price: Number(purchase.price_per_unit),
            currentPrice: price,
            quantity: Number(purchase.quantity),
            transactionType: purchase.transaction_type,
            transactionTypeLabel: purchase.transaction_type_label,
            providerName: purchase.provider.name,
        }))
        .sort((first, second) => first.timestamp - second.timestamp);
}

/**
 * Count how many buy orders were made below the current price.
 */
export function countBuysBelowCurrentPrice(
    points: readonly CryptoDcaChartPoint[],
): CryptoDcaBuyPriceSplit {
    const buys = points.filter((point) => point.transactionType === 'buy');

    return {
        below: buys.filter((point) => point.price < point.currentPrice).length,
        total: buys.length,
    };
}
