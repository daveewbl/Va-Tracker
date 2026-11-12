<?php

namespace Database\Seeders;

use App\Models\Instrument;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Instrument::query()->firstOrCreate(['ticker' => 'SWDA'], [
            'name' => 'iShares Core MSCI World',
        ]);
    }
}
