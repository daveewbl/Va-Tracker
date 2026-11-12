<?php

namespace App\Actions\Concerns;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

trait ValidatesVaInput
{
    /** @param array<string, mixed> $input */
    private function validate(array $input, array $rules, array $messages = []): array
    {
        return Validator::make($input, $rules, $messages)->validate();
    }

    private function money(string $value, string $field, bool $positive = false): int
    {
        try {
            $amount = Decimal::money($value);
        } catch (InvalidArgumentException|OverflowException $exception) {
            throw ValidationException::withMessages([$field => 'Inserisci un importo valido con al massimo due decimali.']);
        }

        if ($positive && $amount <= 0) {
            throw ValidationException::withMessages([$field => 'L’importo deve essere maggiore di zero.']);
        }

        if (! $positive && $amount < 0) {
            throw ValidationException::withMessages([$field => 'L’importo non può essere negativo.']);
        }

        return $amount;
    }

    private function price(string $value, string $field): int
    {
        try {
            return Decimal::price($value);
        } catch (InvalidArgumentException|OverflowException $exception) {
            throw ValidationException::withMessages([$field => 'Inserisci un prezzo positivo con al massimo sei decimali.']);
        }
    }

    private function quantity(mixed $value, string $field): int
    {
        if (! is_int($value) && (! is_string($value) || ! preg_match('/^(0|[1-9]\d*)$/', $value))) {
            throw ValidationException::withMessages([$field => 'Inserisci un numero intero non negativo.']);
        }

        $quantity = filter_var($value, FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 0) {
            throw ValidationException::withMessages([$field => 'Inserisci un numero intero non negativo.']);
        }

        return $quantity;
    }

    private function period(string $value, string $field): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])(?:-01)?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Seleziona un mese valido.']);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', strlen($value) === 7 ? $value.'-01' : $value);

        if (! $date || $date->format(strlen($value) === 7 ? 'Y-m' : 'Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Seleziona un mese valido.']);
        }

        return $date->startOfMonth()->toDateString();
    }

    private function date(string $value, string $field): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Inserisci una data valida.']);
        }

        return $date->toDateString();
    }

    private function fingerprintMismatch(): never
    {
        throw ValidationException::withMessages([
            'expected_fingerprint' => 'I dati sono cambiati dopo l’anteprima. Aggiorna il riepilogo e verifica nuovamente i valori.',
        ]);
    }
}
