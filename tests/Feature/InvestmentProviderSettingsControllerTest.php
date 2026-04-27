<?php

use App\Enums\BalanceSyncProvider;
use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentProvider;
use App\Models\SavingsAccount;
use App\Models\User;

beforeEach(function () {
    InvestmentProvider::factory()->ibkr()->create();
    InvestmentProvider::factory()->ilirika()->create();
});

function investmentProviderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Trade Republic',
        'slug' => '',
        'sort_order' => 5,
        'requires_linked_savings_account' => false,
        'linked_savings_account_id' => null,
        'supported_symbol_types' => [
            InvestmentSymbolType::ETF->value,
            InvestmentSymbolType::STOCK->value,
        ],
        'balance_sync_provider' => null,
    ], $overrides);
}

test('investment provider settings routes require authentication', function () {
    $provider = InvestmentProvider::query()->firstOrFail();

    $this->get(route('investments.providers.index'))->assertRedirect(route('login'));
    $this->get(route('investments.providers.create'))->assertRedirect(route('login'));
    $this->get(route('investments.providers.edit', $provider))->assertRedirect(route('login'));
    $this->post(route('investments.providers.store'), investmentProviderPayload())->assertRedirect(route('login'));
    $this->put(route('investments.providers.update', $provider), investmentProviderPayload())->assertRedirect(route('login'));
});

test('authenticated user can view the investment provider settings pages', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');
    $provider->update([
        'balance_sync_provider' => BalanceSyncProvider::Binance->value,
    ]);
    $parentAccount = SavingsAccount::factory()->create([
        'name' => 'Banka',
    ]);
    $leafAccount = SavingsAccount::factory()->create([
        'parent_id' => $parentAccount->id,
        'name' => 'Rezerva',
    ]);

    $this->actingAs($user)
        ->get(route('investments.providers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Ponudniki')
            ->has('providers', 2)
            ->where('providers.0.slug', 'ibkr')
            ->where('providers.0.supported_symbol_type_labels', ['ETF', 'Delnica', 'Kripto'])
            ->where('providers.1.slug', 'ilirika')
        );

    $this->actingAs($user)
        ->get(route('investments.providers.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/PonudnikiForm')
            ->where('provider', null)
            ->has('typeOptions', 4)
            ->has('syncProviderOptions', 1)
            ->where('syncProviderOptions.0.value', BalanceSyncProvider::Binance->value)
            ->has('savingsAccountOptions', 1)
            ->where('savingsAccountOptions.0.label', 'Banka / Rezerva')
        );

    $this->actingAs($user)
        ->get(route('investments.providers.edit', $provider))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/PonudnikiForm')
            ->where('provider.id', $provider->id)
            ->where('provider.slug', $provider->slug)
            ->where('provider.supported_symbol_types', ['etf', 'stock', 'crypto'])
            ->where('provider.balance_sync_provider', BalanceSyncProvider::Binance->value)
        );
});

test('can store an investment provider from settings', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.providers.store'), investmentProviderPayload([
            'name' => 'Trade Republic',
            'slug' => '',
            'sort_order' => '7',
            'requires_linked_savings_account' => true,
            'linked_savings_account_id' => $linkedAccount->id,
            'supported_symbol_types' => [
                InvestmentSymbolType::ETF->value,
                InvestmentSymbolType::STOCK->value,
                InvestmentSymbolType::CRYPTO->value,
            ],
            'balance_sync_provider' => BalanceSyncProvider::Binance->value,
        ]))
        ->assertRedirect(route('investments.providers.index'));

    $provider = InvestmentProvider::query()->where('name', 'Trade Republic')->firstOrFail();

    expect($provider->slug)->toBe('trade-republic')
        ->and($provider->sort_order)->toBe(7)
        ->and($provider->requires_linked_savings_account)->toBeTrue()
        ->and($provider->linked_savings_account_id)->toBe($linkedAccount->id)
        ->and($provider->supported_symbol_types)->toBe(['etf', 'stock', 'crypto'])
        ->and($provider->balance_sync_provider)->toBe(BalanceSyncProvider::Binance->value);
});

test('can update an investment provider from settings', function () {
    $user = User::factory()->create();
    $linkedAccount = SavingsAccount::factory()->create();
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ilirika');

    $provider->update([
        'linked_savings_account_id' => $linkedAccount->id,
        'requires_linked_savings_account' => false,
        'supported_symbol_types' => [InvestmentSymbolType::BOND->value],
        'balance_sync_provider' => null,
    ]);

    $this->actingAs($user)
        ->put(route('investments.providers.update', $provider), investmentProviderPayload([
            'name' => 'Ilirika Plus',
            'slug' => '',
            'sort_order' => 9,
            'requires_linked_savings_account' => true,
            'linked_savings_account_id' => $linkedAccount->id,
            'supported_symbol_types' => [
                InvestmentSymbolType::BOND->value,
                InvestmentSymbolType::CRYPTO->value,
            ],
            'balance_sync_provider' => BalanceSyncProvider::Binance->value,
        ]))
        ->assertRedirect(route('investments.providers.index'));

    expect($provider->fresh()->name)->toBe('Ilirika Plus')
        ->and($provider->fresh()->slug)->toBe('ilirika-plus')
        ->and($provider->fresh()->sort_order)->toBe(9)
        ->and($provider->fresh()->requires_linked_savings_account)->toBeTrue()
        ->and($provider->fresh()->linked_savings_account_id)->toBe($linkedAccount->id)
        ->and($provider->fresh()->supported_symbol_types)->toBe(['bond', 'crypto'])
        ->and($provider->fresh()->balance_sync_provider)->toBe(BalanceSyncProvider::Binance->value);
});

test('investment provider settings reject non leaf linked savings accounts', function () {
    $user = User::factory()->create();
    $parentAccount = SavingsAccount::factory()->create();
    SavingsAccount::factory()->create([
        'parent_id' => $parentAccount->id,
    ]);

    $this->actingAs($user)
        ->post(route('investments.providers.store'), investmentProviderPayload([
            'requires_linked_savings_account' => true,
            'linked_savings_account_id' => $parentAccount->id,
        ]))
        ->assertSessionHasErrors('linked_savings_account_id');
});

test('investment provider settings enforce unique slugs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.providers.store'), investmentProviderPayload([
            'name' => 'Drug ponudnik',
            'slug' => 'ibkr',
        ]))
        ->assertSessionHasErrors('slug');
});

test('investment provider settings reject sync provider for non crypto providers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investments.providers.store'), investmentProviderPayload([
            'supported_symbol_types' => [InvestmentSymbolType::BOND->value],
            'balance_sync_provider' => BalanceSyncProvider::Binance->value,
        ]))
        ->assertSessionHasErrors('balance_sync_provider');
});

test('investment provider settings route does not collide with provider detail pages', function () {
    $user = User::factory()->create();
    $provider = InvestmentProvider::query()->firstWhere('slug', 'ibkr');

    $this->actingAs($user)
        ->get(route('investments.providers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Investicije/Ponudniki'));

    $this->actingAs($user)
        ->get(route('investments.providers.show', $provider->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Investicije/Provider')
            ->where('provider.slug', $provider->slug)
        );
});
