<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class Decimal
{
    public static function money(string $amount): int
    {
        return self::parse($amount, 2, 100);
    }

    public static function price(string $amount): int
    {
        $price = self::parse($amount, 6, 1_000_000);

        if ($price <= 0) {
            throw new InvalidArgumentException('The unit price must be greater than zero.');
        }

        return $price;
    }

    public static function formatMoney(int $cents): string
    {
        return self::format($cents, 100, 2, false);
    }

    public static function formatPrice(int $micros): string
    {
        return self::format($micros, 1_000_000, 6, true);
    }

    public static function roundMoney(int $micros): int
    {
        $rounded = intdiv($micros, 10_000);
        $remainder = $micros % 10_000;

        if (abs($remainder) >= 5_000) {
            $rounded += $micros < 0 ? -1 : 1;
        }

        return $rounded;
    }

    public static function value(int $quantity, int $priceMicros): int
    {
        if ($quantity < 0 || $priceMicros < 0) {
            throw new InvalidArgumentException('Quantity and price must not be negative.');
        }

        return self::roundMoney(self::multiply($quantity, $priceMicros));
    }

    public static function multiply(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        $overflows = ($left > 0 && $right > 0 && $left > intdiv(PHP_INT_MAX, $right))
            || ($left > 0 && $right < 0 && $right < intdiv(PHP_INT_MIN, $left))
            || ($left < 0 && $right > 0 && $left < intdiv(PHP_INT_MIN, $right))
            || ($left < 0 && $right < 0 && $left < intdiv(PHP_INT_MAX, $right));

        if ($overflows) {
            throw new OverflowException('The exact calculation exceeds the supported integer range.');
        }

        return $left * $right;
    }

    public static function add(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right || $right < 0 && $left < PHP_INT_MIN - $right) {
            throw new OverflowException('The exact calculation exceeds the supported integer range.');
        }

        return $left + $right;
    }

    private static function parse(string $amount, int $scale, int $factor): int
    {
        $amount = trim($amount);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('The amount must be a plain decimal number.');
        }

        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("The amount supports at most {$scale} decimal places.");
        }

        $whole = (int) $matches[2];
        $fractional = (int) str_pad($fraction, $scale, '0');

        if ($whole > intdiv(PHP_INT_MAX - $fractional, $factor)) {
            throw new OverflowException('The amount exceeds the supported integer range.');
        }

        $value = $whole * $factor + $fractional;

        return ($matches[1] ?? '') === '-' ? -$value : $value;
    }

    private static function format(int $value, int $factor, int $scale, bool $trim): string
    {
        if ($value === PHP_INT_MIN) {
            throw new OverflowException('The exact calculation exceeds the supported formatting range.');
        }

        $negative = $value < 0;
        $absolute = abs($value);
        $whole = intdiv($absolute, $factor);
        $fraction = str_pad((string) ($absolute % $factor), $scale, '0', STR_PAD_LEFT);

        if ($trim) {
            $fraction = rtrim($fraction, '0');
        }

        $formatted = (string) $whole;

        if ($fraction !== '') {
            $formatted .= '.'.$fraction;
        }

        return ($negative ? '-' : '').$formatted;
    }
}
