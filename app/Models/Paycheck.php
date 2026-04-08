<?php

namespace App\Models;

use Database\Factories\PaycheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paycheck_year_id', 'month', 'net', 'gross', 'contributions', 'taxes'])]
class Paycheck extends Model
{
    /** @use HasFactory<PaycheckFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'net' => 'decimal:2',
            'gross' => 'decimal:2',
            'contributions' => 'decimal:2',
            'taxes' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PaycheckYear, $this> */
    public function paycheckYear(): BelongsTo
    {
        return $this->belongsTo(PaycheckYear::class);
    }
}
