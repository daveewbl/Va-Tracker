<?php

namespace App\Data;

use Spatie\LaravelData\Data;

final class InvestmentData extends Data
{
    public function __construct(
        public string $period,
        public int $unitPriceMicros,
        public string $priceDate,
        public int $purchasedQuantity,
    ) {}

    /** @return array{period: string, price: int, price_date: string, purchased_quantity: int} */
    public function fingerprintValues(): array
    {
        return [
            'period' => $this->period,
            'price' => $this->unitPriceMicros,
            'price_date' => $this->priceDate,
            'purchased_quantity' => $this->purchasedQuantity,
        ];
    }
}
