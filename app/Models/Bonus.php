<?php

namespace App\Models;

use Database\Factories\BonusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paycheck_year_id', 'type', 'amount', 'description', 'paid_at'])]
class Bonus extends Model
{
    /** @use HasFactory<BonusFactory> */
    use HasFactory;

    protected $table = 'paycheck_bonuses';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /** @return BelongsTo<PaycheckYear, $this> */
    public function paycheckYear(): BelongsTo
    {
        return $this->belongsTo(PaycheckYear::class);
    }
}
