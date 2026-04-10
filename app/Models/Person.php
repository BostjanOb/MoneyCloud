<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'is_active', 'sort_order'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<PaycheckYear, $this> */
    public function paycheckYears(): HasMany
    {
        return $this->hasMany(PaycheckYear::class);
    }

    /** @return HasMany<SavingsAccount, $this> */
    public function savingsAccounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }
}
