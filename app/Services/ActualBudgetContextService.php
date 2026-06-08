<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Throwable;

class ActualBudgetContextService
{
    public const CACHE_KEY = 'actual_budget.chat_context';

    public const REPORT_CONTEXT_KEY = 'actual_budget.report_context';

    public const STALE_WARNING = 'Podatki iz Actual Budget so iz predpomnilnika.';

    private const TRANSACTION_WINDOW_DAYS = 90;

    public function __construct(private readonly ActualBudgetClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshChatContext(): array
    {
        if (! $this->isConfigured()) {
            return $this->unavailableContext([
                'Actual Budget ni nastavljen.',
            ]);
        }

        $context = $this->liveContext(source: 'cache');

        Cache::forever(self::CACHE_KEY, $context);

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    public function reportContext(): array
    {
        if (! $this->isConfigured()) {
            return $this->unavailableContext([]);
        }

        try {
            return $this->liveContext(source: 'live');
        } catch (Throwable $exception) {
            report($exception);

            $cached = $this->cachedContext();

            if ($cached !== null) {
                $cached['source'] = 'cache';
                $cached['warnings'] = array_values(array_unique([
                    self::STALE_WARNING,
                    ...($cached['warnings'] ?? []),
                ]));

                return $cached;
            }

            return $this->unavailableContext([
                'Podatki iz Actual Budget trenutno niso na voljo.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cachedContext(): ?array
    {
        $context = Cache::get(self::CACHE_KEY);

        return is_array($context) ? $context : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        if (! $this->isConfigured()) {
            return [
                'configured' => false,
                'available' => false,
                'generated_at' => null,
                'transaction_count' => 0,
                'account_count' => 0,
                'warnings' => [],
            ];
        }

        $context = $this->cachedContext();

        if ($context === null || ($context['available'] ?? false) !== true) {
            return [
                'configured' => $this->isConfigured(),
                'available' => false,
                'generated_at' => null,
                'transaction_count' => 0,
                'account_count' => 0,
                'warnings' => [],
            ];
        }

        return [
            'configured' => true,
            'available' => true,
            'generated_at' => $context['generated_at'] ?? null,
            'window' => $context['window'] ?? null,
            'transaction_count' => count($context['transactions'] ?? []),
            'account_count' => count($context['accounts'] ?? []),
            'warnings' => $context['warnings'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contextForTools(): array
    {
        if (! $this->isConfigured()) {
            return $this->unavailableContext([]);
        }

        if (Context::hasHidden(self::REPORT_CONTEXT_KEY)) {
            $context = Context::getHidden(self::REPORT_CONTEXT_KEY);

            if (is_array($context)) {
                return $context;
            }
        }

        return $this->cachedContext() ?? $this->unavailableContext([
            'Najprej ročno osveži Actual Budget podatke v klepetu.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function budgetOverview(): array
    {
        $context = $this->contextForTools();

        return [
            'configured' => $context['configured'] ?? $this->isConfigured(),
            'available' => $context['available'] ?? false,
            'source' => $context['source'] ?? null,
            'generated_at' => $context['generated_at'] ?? null,
            'window' => $context['window'] ?? null,
            'warnings' => $context['warnings'] ?? [],
            'currency' => 'EUR',
            'account_count' => count($context['accounts'] ?? []),
            'transaction_count' => count($context['transactions'] ?? []),
            'budget_months' => $context['budget_months'] ?? [],
        ];
    }

    /**
     * @param  array{account_id?: string|null, category_id?: string|null, since?: string|null, until?: string|null, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function transactions(array $filters = []): array
    {
        $context = $this->contextForTools();
        $transactions = collect($context['transactions'] ?? [])
            ->filter(fn (array $transaction): bool => $this->matchesTransactionFilters($transaction, $filters))
            ->sortByDesc('date')
            ->values();

        $limit = min(max((int) ($filters['limit'] ?? 300), 1), 1000);
        $limited = $transactions->take($limit)->values();

        return [
            'configured' => $context['configured'] ?? $this->isConfigured(),
            'available' => $context['available'] ?? false,
            'source' => $context['source'] ?? null,
            'generated_at' => $context['generated_at'] ?? null,
            'window' => $context['window'] ?? null,
            'warnings' => $context['warnings'] ?? [],
            'currency' => 'EUR',
            'total_matching' => $transactions->count(),
            'returned' => $limited->count(),
            'truncated' => $transactions->count() > $limited->count(),
            'transactions' => $limited->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function spendingByCategory(): array
    {
        $context = $this->contextForTools();
        $categories = collect($context['transactions'] ?? [])
            ->reject(fn (array $transaction): bool => (bool) ($transaction['is_transfer'] ?? false))
            ->groupBy(fn (array $transaction): string => (string) ($transaction['category_id'] ?? 'uncategorized'))
            ->map(fn (Collection $transactions): array => $this->summarizeCategoryTransactions($transactions))
            ->sortByDesc('spent_eur')
            ->values()
            ->all();

        return [
            'configured' => $context['configured'] ?? $this->isConfigured(),
            'available' => $context['available'] ?? false,
            'source' => $context['source'] ?? null,
            'generated_at' => $context['generated_at'] ?? null,
            'window' => $context['window'] ?? null,
            'warnings' => $context['warnings'] ?? [],
            'currency' => 'EUR',
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function liveContext(string $source): array
    {
        $today = CarbonImmutable::now('Europe/Ljubljana');
        $since = $today->subDays(self::TRANSACTION_WINDOW_DAYS);

        $accounts = $this->client->accounts();
        $categoryGroups = $this->normalizeCategoryGroups($this->client->categoryGroups());
        $categories = $this->normalizeCategories($this->client->categories(), $categoryGroups);
        $payees = $this->normalizePayees($this->client->payees());

        $transactionsByAccount = collect($accounts)
            ->mapWithKeys(fn (array $account): array => [
                (string) $account['id'] => $this->client->transactions((string) $account['id'], $since, $today),
            ]);

        $normalizedAccounts = collect($accounts)
            ->filter(fn (array $account): bool => ! ($account['closed'] ?? false)
                || count($transactionsByAccount->get((string) $account['id'], [])) > 0)
            ->map(fn (array $account): array => $this->normalizeAccount($account))
            ->values()
            ->all();

        $accountMap = collect($normalizedAccounts)->keyBy('id')->all();
        $transactions = $transactionsByAccount
            ->flatMap(fn (array $transactions, string $accountId): array => array_map(
                fn (array $transaction): array => $this->normalizeTransaction(
                    $transaction,
                    $accountMap[$accountId] ?? null,
                    $categories,
                    $payees,
                ),
                $transactions,
            ))
            ->sortByDesc('date')
            ->values()
            ->all();

        return [
            'configured' => true,
            'available' => true,
            'source' => $source,
            'generated_at' => $today->toIso8601String(),
            'window' => [
                'days' => self::TRANSACTION_WINDOW_DAYS,
                'since' => $since->toDateString(),
                'until' => $today->toDateString(),
            ],
            'warnings' => [],
            'currency' => 'EUR',
            'accounts' => $normalizedAccounts,
            'category_groups' => array_values($categoryGroups),
            'categories' => array_values($categories),
            'payees' => array_values($payees),
            'budget_months' => $this->budgetMonths($since, $today, $categoryGroups, $categories),
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableContext(array $warnings): array
    {
        return [
            'configured' => $this->isConfigured(),
            'available' => false,
            'source' => null,
            'generated_at' => null,
            'window' => null,
            'warnings' => $warnings,
            'currency' => 'EUR',
            'accounts' => [],
            'category_groups' => [],
            'categories' => [],
            'payees' => [],
            'budget_months' => [],
            'transactions' => [],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeCategoryGroups(array $groups): array
    {
        return collect($groups)
            ->mapWithKeys(fn (array $group): array => [
                (string) $group['id'] => [
                    'id' => (string) $group['id'],
                    'name' => (string) ($group['name'] ?? ''),
                    'is_income' => (bool) ($group['is_income'] ?? false),
                    'hidden' => (bool) ($group['hidden'] ?? false),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeCategories(array $categories, array $groups): array
    {
        return collect($categories)
            ->mapWithKeys(function (array $category) use ($groups): array {
                $groupId = (string) ($category['group_id'] ?? '');
                $group = $groups[$groupId] ?? null;

                return [
                    (string) $category['id'] => [
                        'id' => (string) $category['id'],
                        'name' => (string) ($category['name'] ?? 'Brez kategorije'),
                        'group_id' => $groupId,
                        'group_name' => $group['name'] ?? null,
                        'is_income' => (bool) ($category['is_income'] ?? false),
                        'hidden' => (bool) ($category['hidden'] ?? false),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizePayees(array $payees): array
    {
        return collect($payees)
            ->mapWithKeys(fn (array $payee): array => [
                (string) $payee['id'] => [
                    'id' => (string) $payee['id'],
                    'name' => (string) ($payee['name'] ?? ''),
                    'category_id' => $payee['category'] ?? null,
                    'transfer_account_id' => $payee['transfer_acct'] ?? null,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAccount(array $account): array
    {
        return [
            'id' => (string) $account['id'],
            'name' => (string) ($account['name'] ?? ''),
            'offbudget' => (bool) ($account['offbudget'] ?? false),
            'closed' => (bool) ($account['closed'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $account
     * @param  array<string, array<string, mixed>>  $categories
     * @param  array<string, array<string, mixed>>  $payees
     * @return array<string, mixed>
     */
    private function normalizeTransaction(array $transaction, ?array $account, array $categories, array $payees): array
    {
        $categoryId = $transaction['category'] ?? null;
        $category = is_string($categoryId) ? ($categories[$categoryId] ?? null) : null;
        $payeeId = $transaction['payee'] ?? null;
        $payee = is_string($payeeId) ? ($payees[$payeeId] ?? null) : null;
        $amountRaw = (int) ($transaction['amount'] ?? 0);
        $amountEur = $this->amountToEuro($amountRaw);

        return [
            'id' => $transaction['id'] ?? null,
            'date' => $transaction['date'] ?? null,
            'account_id' => $transaction['account'] ?? ($account['id'] ?? null),
            'account_name' => $account['name'] ?? null,
            'account_offbudget' => $account['offbudget'] ?? null,
            'account_closed' => $account['closed'] ?? null,
            'amount_raw' => $amountRaw,
            'amount_eur' => $amountEur,
            'amount_formatted' => $this->formatEuro($amountEur),
            'payee_id' => $payeeId,
            'payee_name' => $payee['name'] ?? ($transaction['payee_name'] ?? null),
            'imported_payee' => $transaction['imported_payee'] ?? null,
            'category_id' => $categoryId,
            'category_name' => $category['name'] ?? 'Brez kategorije',
            'category_group_id' => $category['group_id'] ?? null,
            'category_group_name' => $category['group_name'] ?? null,
            'category_hidden' => $category['hidden'] ?? null,
            'notes' => $transaction['notes'] ?? null,
            'imported_id' => $transaction['imported_id'] ?? null,
            'transfer_id' => $transaction['transfer_id'] ?? null,
            'is_transfer' => filled($transaction['transfer_id'] ?? null),
            'cleared' => $transaction['cleared'] ?? null,
            'subtransactions' => collect($transaction['subtransactions'] ?? [])
                ->map(fn (array $subtransaction): array => $this->normalizeTransaction($subtransaction, $account, $categories, $payees))
                ->all(),
            'raw' => $transaction,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function budgetMonths(CarbonImmutable $since, CarbonImmutable $until, array $groups, array $categories): array
    {
        $month = $since->startOfMonth();
        $lastMonth = $until->startOfMonth();
        $months = [];

        while ($month->lessThanOrEqualTo($lastMonth)) {
            $months[] = $this->normalizeBudgetMonth(
                $this->client->budgetMonth($month->format('Y-m')),
                $groups,
                $categories,
            );
            $month = $month->addMonth();
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBudgetMonth(array $budgetMonth, array $groups, array $categories): array
    {
        return [
            'month' => $budgetMonth['month'] ?? null,
            'income_available_eur' => $this->amountToEuro((int) ($budgetMonth['incomeAvailable'] ?? 0)),
            'total_budgeted_eur' => $this->amountToEuro((int) ($budgetMonth['totalBudgeted'] ?? 0)),
            'total_income_eur' => $this->amountToEuro((int) ($budgetMonth['totalIncome'] ?? 0)),
            'total_spent_eur' => $this->amountToEuro((int) ($budgetMonth['totalSpent'] ?? 0)),
            'total_balance_eur' => $this->amountToEuro((int) ($budgetMonth['totalBalance'] ?? 0)),
            'category_groups' => collect($budgetMonth['categoryGroups'] ?? [])
                ->map(fn (array $group): array => $this->normalizeBudgetCategoryGroup($group, $groups, $categories))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBudgetCategoryGroup(array $group, array $groups, array $categories): array
    {
        $groupId = (string) ($group['id'] ?? '');

        return [
            'id' => $groupId,
            'name' => $groups[$groupId]['name'] ?? ($group['name'] ?? ''),
            'is_income' => (bool) ($group['is_income'] ?? false),
            'hidden' => (bool) ($group['hidden'] ?? false),
            'budgeted_eur' => $this->amountToEuro((int) ($group['budgeted'] ?? 0)),
            'spent_eur' => $this->amountToEuro((int) ($group['spent'] ?? 0)),
            'balance_eur' => $this->amountToEuro((int) ($group['balance'] ?? 0)),
            'categories' => collect($group['categories'] ?? [])
                ->map(fn (array $category): array => $this->normalizeBudgetCategory($category, $categories))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBudgetCategory(array $category, array $categories): array
    {
        $categoryId = (string) ($category['id'] ?? '');
        $reference = $categories[$categoryId] ?? null;

        return [
            'id' => $categoryId,
            'name' => $reference['name'] ?? ($category['name'] ?? 'Brez kategorije'),
            'group_id' => $category['group_id'] ?? ($reference['group_id'] ?? null),
            'is_income' => (bool) ($category['is_income'] ?? false),
            'hidden' => (bool) ($category['hidden'] ?? false),
            'budgeted_eur' => $this->amountToEuro((int) ($category['budgeted'] ?? 0)),
            'spent_eur' => $this->amountToEuro((int) ($category['spent'] ?? 0)),
            'balance_eur' => $this->amountToEuro((int) ($category['balance'] ?? 0)),
            'carryover' => (bool) ($category['carryover'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @param  array{account_id?: string|null, category_id?: string|null, since?: string|null, until?: string|null, limit?: int|null}  $filters
     */
    private function matchesTransactionFilters(array $transaction, array $filters): bool
    {
        if (filled($filters['account_id'] ?? null) && $transaction['account_id'] !== $filters['account_id']) {
            return false;
        }

        if (filled($filters['category_id'] ?? null) && $transaction['category_id'] !== $filters['category_id']) {
            return false;
        }

        if (filled($filters['since'] ?? null) && strcmp((string) $transaction['date'], (string) $filters['since']) < 0) {
            return false;
        }

        if (filled($filters['until'] ?? null) && strcmp((string) $transaction['date'], (string) $filters['until']) > 0) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $transactions
     * @return array<string, mixed>
     */
    private function summarizeCategoryTransactions(Collection $transactions): array
    {
        $first = $transactions->first();
        $expenseRaw = $transactions->sum(fn (array $transaction): int => min(0, (int) $transaction['amount_raw']));
        $incomeRaw = $transactions->sum(fn (array $transaction): int => max(0, (int) $transaction['amount_raw']));

        return [
            'category_id' => $first['category_id'] ?? null,
            'category_name' => $first['category_name'] ?? 'Brez kategorije',
            'category_group_id' => $first['category_group_id'] ?? null,
            'category_group_name' => $first['category_group_name'] ?? null,
            'hidden' => $first['category_hidden'] ?? null,
            'transaction_count' => $transactions->count(),
            'spent_eur' => abs($this->amountToEuro($expenseRaw)),
            'income_eur' => $this->amountToEuro($incomeRaw),
            'net_eur' => $this->amountToEuro($expenseRaw + $incomeRaw),
            'top_payees' => $transactions
                ->groupBy(fn (array $transaction): string => (string) ($transaction['payee_name'] ?? $transaction['imported_payee'] ?? 'Neznan prejemnik'))
                ->map(fn (Collection $payeeTransactions, string $payee): array => [
                    'payee' => $payee,
                    'spent_eur' => abs($this->amountToEuro($payeeTransactions->sum(fn (array $transaction): int => min(0, (int) $transaction['amount_raw'])))),
                    'transaction_count' => $payeeTransactions->count(),
                ])
                ->sortByDesc('spent_eur')
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function amountToEuro(int $amount): float
    {
        return round($amount / 100, 2);
    }

    private function formatEuro(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
