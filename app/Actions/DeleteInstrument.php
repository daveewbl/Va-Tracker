<?php

namespace App\Actions;

use App\Models\Instrument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteInstrument
{
    use AsAction;

    public function handle(User $user, Instrument $instrument): void
    {
        DB::transaction(function () use ($instrument): void {
            $current = Instrument::query()->find($instrument->getKey());

            if (! $current) {
                throw ValidationException::withMessages(['instrument' => 'Il titolo non è più disponibile.']);
            }

            if ($current->plans()->exists()) {
                throw ValidationException::withMessages(['instrument' => 'Non puoi eliminare un titolo associato a un PAC.']);
            }

            $current->delete();
        }, attempts: 5);
    }
}
