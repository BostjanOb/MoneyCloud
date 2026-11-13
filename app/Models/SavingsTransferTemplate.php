<?php

namespace App\Models;

use Database\Factories\SavingsTransferTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'source_account_id', 'sort_order'])]
class SavingsTransferTemplate extends Model
{
    /** @use HasFactory<SavingsTransferTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source_account_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<SavingsAccount, $this> */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'source_account_id');
    }

    /** @return HasMany<SavingsTransferTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SavingsTransferTemplateItem::class)->orderBy('id');
    }

    public function totalInCents(): int
    {
        return $this->items->sum(fn (SavingsTransferTemplateItem $item): int => SavingsAccount::toCents($item->amount));
    }
}
