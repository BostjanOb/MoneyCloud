<?php

namespace App\Models;

use Database\Factories\SavingsAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'person_id', 'name', 'owner', 'amount', 'apy', 'sort_order'])]
class SavingsAccount extends Model
{
    /** @use HasFactory<SavingsAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'person_id' => 'integer',
            'amount' => 'decimal:2',
            'apy' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<SavingsAccount> $query */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** @return BelongsTo<SavingsAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return HasMany<SavingsAccount, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function hasChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }

    public function syncAmountFromChildren(): void
    {
        $amountInCents = $this->children()
            ->get(['id', 'amount'])
            ->sum(fn (self $child): int => self::toCents($child->amount));

        $this->forceFill([
            'amount' => self::fromCents($amountInCents),
        ])->save();
    }

    public static function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function fromCents(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }
}
