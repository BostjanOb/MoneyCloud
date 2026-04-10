<?php

namespace App\Models;

use Database\Factories\PaycheckYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['person_id', 'year', 'child1_months', 'child2_months', 'child3_months'])]
class PaycheckYear extends Model
{
    /** @use HasFactory<PaycheckYearFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'person_id' => 'integer',
            'year' => 'integer',
            'child1_months' => 'integer',
            'child2_months' => 'integer',
            'child3_months' => 'integer',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return HasMany<Paycheck, $this> */
    public function paychecks(): HasMany
    {
        return $this->hasMany(Paycheck::class)->orderBy('month');
    }

    /** @return HasMany<Bonus, $this> */
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }
}
