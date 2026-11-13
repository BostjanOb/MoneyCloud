<?php

namespace App\Models;

use Database\Factories\SavingsTransferTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['savings_transfer_template_id', 'savings_account_id', 'amount'])]
class SavingsTransferTemplateItem extends Model
{
    /** @use HasFactory<SavingsTransferTemplateItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'savings_transfer_template_id' => 'integer',
            'savings_account_id' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<SavingsTransferTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SavingsTransferTemplate::class, 'savings_transfer_template_id');
    }

    /** @return BelongsTo<SavingsAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }
}
