<?php

namespace App\Services;

use App\Enums\InvestmentSymbolType;
use App\Enums\InvestmentTransactionType;
use App\Models\CryptoBalance;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CryptoDcaPurchaseService
{
    private const BINANCE_CSV_HEADER = [
        'Create Time',
        'Wallet',
        'Frequency',
        'Hourly',
        'From Amount',
        'From Coin',
        'To Amount',
        'To Coin',
        'Price',
        'Inverse Price',
        'Settlement Date',
        'Plan ID',
        'Status',
    ];

    /**
     * @param  array<string, mixed>  $purchaseAttributes
     */
    public function store(
        array $purchaseAttributes,
        bool $addToBalance,
        ?int $balanceProviderId,
    ): InvestmentPurchase {
        return DB::transaction(function () use ($purchaseAttributes, $addToBalance, $balanceProviderId): InvestmentPurchase {
            $purchase = InvestmentPurchase::query()->create($purchaseAttributes);

            if ($addToBalance && $balanceProviderId !== null) {
                $this->adjustBalanceQuantity(
                    $balanceProviderId,
                    (int) $purchaseAttributes['investment_symbol_id'],
                    (string) $purchaseAttributes['quantity'],
                    InvestmentTransactionType::from((string) $purchaseAttributes['transaction_type']),
                );
            }

            return $purchase;
        });
    }

    /**
     * @return array{created: int, skipped_duplicate: int, skipped_status: int, skipped_currency: int, skipped_symbols: list<string>}
     */
    public function importBinanceCsv(
        UploadedFile $file,
        int $providerId,
        bool $addToBalance,
    ): array {
        $rows = $this->readBinanceCsvRows($file);
        $symbolsByTicker = InvestmentSymbol::query()
            ->where('type', InvestmentSymbolType::CRYPTO)
            ->get()
            ->keyBy('symbol');

        return DB::transaction(function () use ($rows, $providerId, $addToBalance, $symbolsByTicker): array {
            $created = 0;
            $skippedDuplicate = 0;
            $skippedStatus = 0;
            $skippedCurrency = 0;
            $skippedSymbols = [];

            foreach ($rows as $row) {
                if ($row['status'] !== 'SUCCESS') {
                    $skippedStatus++;

                    continue;
                }

                if ($row['from_coin'] !== 'EUR') {
                    $skippedCurrency++;

                    continue;
                }

                $symbol = $symbolsByTicker->get($row['to_coin']);

                if (! $symbol instanceof InvestmentSymbol) {
                    if (! in_array($row['to_coin'], $skippedSymbols, true)) {
                        $skippedSymbols[] = $row['to_coin'];
                    }

                    continue;
                }

                $exists = InvestmentPurchase::query()
                    ->where('investment_provider_id', $providerId)
                    ->where('investment_symbol_id', $symbol->id)
                    ->where('purchased_at', $row['purchased_at'])
                    ->where('transaction_type', InvestmentTransactionType::Buy->value)
                    ->exists();

                if ($exists) {
                    $skippedDuplicate++;

                    continue;
                }

                InvestmentPurchase::query()->create([
                    'investment_provider_id' => $providerId,
                    'investment_symbol_id' => $symbol->id,
                    'purchased_at' => $row['purchased_at'],
                    'transaction_type' => InvestmentTransactionType::Buy->value,
                    'quantity' => $row['quantity'],
                    'price_per_unit' => $row['price_per_unit'],
                    'fee' => '0.00',
                ]);

                if ($addToBalance) {
                    $this->adjustBalanceQuantity(
                        $providerId,
                        $symbol->id,
                        $row['quantity'],
                        InvestmentTransactionType::Buy,
                    );
                }

                $created++;
            }

            return [
                'created' => $created,
                'skipped_duplicate' => $skippedDuplicate,
                'skipped_status' => $skippedStatus,
                'skipped_currency' => $skippedCurrency,
                'skipped_symbols' => $skippedSymbols,
            ];
        });
    }

    /**
     * @return list<array{purchased_at: string, from_coin: string, to_coin: string, quantity: string, price_per_unit: string, status: string}>
     */
    private function readBinanceCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('CSV datoteke ni mogoče odpreti.');
        }

        try {
            $header = fgetcsv($handle);

            if (! is_array($header)) {
                throw new RuntimeException('CSV datoteka je prazna.');
            }

            $this->validateBinanceHeader($header);

            $rows = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                if (count($row) !== count(self::BINANCE_CSV_HEADER)) {
                    throw new RuntimeException("Vrstica {$lineNumber}: pričakovanih je ".count(self::BINANCE_CSV_HEADER).' stolpcev.');
                }

                $rows[] = $this->parseBinanceRow($row, $lineNumber);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $header
     */
    private function validateBinanceHeader(array $header): void
    {
        $normalized = array_map(
            fn (?string $value): string => trim(str_replace("\u{FEFF}", '', (string) $value)),
            $header,
        );

        if ($normalized !== self::BINANCE_CSV_HEADER) {
            throw new RuntimeException('Neveljavna CSV glava. Pričakovan je Binance Recurring Convert izvoz.');
        }
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array{purchased_at: string, from_coin: string, to_coin: string, quantity: string, price_per_unit: string, status: string}
     */
    private function parseBinanceRow(array $row, int $lineNumber): array
    {
        $createTime = trim((string) ($row[0] ?? ''));
        $fromCoin = trim((string) ($row[5] ?? ''));
        $toAmount = trim((string) ($row[6] ?? ''));
        $toCoin = trim((string) ($row[7] ?? ''));
        $price = trim((string) ($row[8] ?? ''));
        $status = trim((string) ($row[12] ?? ''));

        $purchasedAt = CarbonImmutable::createFromFormat('y-m-d H:i:s', $createTime);

        if ($purchasedAt === false) {
            throw new RuntimeException("Vrstica {$lineNumber}: neveljaven datum [{$createTime}].");
        }

        return [
            'purchased_at' => $purchasedAt->format('Y-m-d H:i:s'),
            'from_coin' => $fromCoin,
            'to_coin' => $toCoin,
            'quantity' => number_format((float) $toAmount, 8, '.', ''),
            'price_per_unit' => number_format((float) $price, 3, '.', ''),
            'status' => $status,
        ];
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function adjustBalanceQuantity(
        int $providerId,
        int $symbolId,
        string $quantity,
        InvestmentTransactionType $transactionType,
    ): void {
        $balance = CryptoBalance::query()
            ->where('investment_provider_id', $providerId)
            ->where('investment_symbol_id', $symbolId)
            ->lockForUpdate()
            ->first();

        if ($balance instanceof CryptoBalance) {
            $newQuantity = $transactionType === InvestmentTransactionType::Buy
                ? $this->addQuantities($balance->manual_quantity, $quantity)
                : $this->subtractQuantities($balance->manual_quantity, $quantity);

            $balance->update([
                'manual_quantity' => $newQuantity,
            ]);

            return;
        }

        CryptoBalance::query()->create([
            'investment_provider_id' => $providerId,
            'investment_symbol_id' => $symbolId,
            'manual_quantity' => $transactionType === InvestmentTransactionType::Buy
                ? $this->formatQuantity($quantity)
                : $this->formatQuantity(0),
        ]);
    }

    private function addQuantities(string|int|float $left, string|int|float $right): string
    {
        return $this->formatQuantity(((float) $left) + ((float) $right));
    }

    private function subtractQuantities(string|int|float $left, string|int|float $right): string
    {
        return $this->formatQuantity(max(((float) $left) - ((float) $right), 0));
    }

    private function formatQuantity(string|int|float $quantity): string
    {
        return number_format((float) $quantity, 8, '.', '');
    }
}
