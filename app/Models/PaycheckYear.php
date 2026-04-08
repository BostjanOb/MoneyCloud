<?php

namespace App\Models;

use App\Enums\Employee;
use Database\Factories\PaycheckYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee', 'year', 'child1_months', 'child2_months', 'child3_months'])]
class PaycheckYear extends Model
{
    /** @use HasFactory<PaycheckYearFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'employee' => Employee::class,
            'year' => 'integer',
            'child1_months' => 'integer',
            'child2_months' => 'integer',
            'child3_months' => 'integer',
        ];
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
