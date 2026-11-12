<?php

namespace App\Models;

use App\Casts\UnitPriceCast;
use Database\Factories\InstrumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['ticker', 'name', 'latest_price', 'latest_price_date'])]
class Instrument extends Model
{
    /** @use HasFactory<InstrumentFactory> */
    use HasFactory;

    public function setTickerAttribute(string $ticker): void
    {
        $this->attributes['ticker'] = mb_strtoupper(trim($ticker));
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function investments(): HasManyThrough
    {
        return $this->hasManyThrough(Investment::class, Plan::class);
    }

    protected function casts(): array
    {
        return [
            'latest_price' => UnitPriceCast::class,
            'latest_price_date' => 'date:Y-m-d',
        ];
    }
}
