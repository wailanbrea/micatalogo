<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Convert an arbitrary monetary representation to integer cents deterministically.
     * Rejects invalid inputs (e.g. 'abc', '1,2,3', '--') without float rounding artifacts.
     *
     * @param  string|int|float|null  $value
     *
     * @throws InvalidArgumentException
     */
    public static function toCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * 100;
        }

        $raw = trim((string) $value);

        // Remove currency symbols and surrounding whitespace
        $clean = preg_replace('/^(RD\$|\$|DOP|USD|\s)+/i', '', $raw);
        $clean = trim((string) $clean);

        if ($clean === '' || $clean === '0') {
            return 0;
        }

        $isNegative = str_starts_with($clean, '-');
        if ($isNegative) {
            $clean = substr($clean, 1);
        }

        // Prohibit double signs or malformed characters
        if (str_contains($clean, '-') || str_contains($clean, '+')) {
            throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
        }

        // Check for invalid non-numeric characters (only digits, dot, comma allowed)
        if (! preg_match('/^[0-9.,]+$/', $clean)) {
            throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
        }

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        $whole = '0';
        $fraction = '00';

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');

            if ($lastComma > $lastDot) {
                // European format: 1.250,50 -> thousands dot, decimal comma
                $cleanWithoutThousands = str_replace('.', '', $clean);
                $parts = explode(',', $cleanWithoutThousands);
                if (count($parts) !== 2 || strlen($parts[1]) > 2) {
                    throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
                }
                $whole = $parts[0];
                $fraction = str_pad($parts[1], 2, '0');
            } else {
                // Standard format: 1,250.50 -> thousands comma, decimal dot
                $cleanWithoutThousands = str_replace(',', '', $clean);
                $parts = explode('.', $cleanWithoutThousands);
                if (count($parts) !== 2 || strlen($parts[1]) > 2) {
                    throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
                }
                $whole = $parts[0];
                $fraction = str_pad($parts[1], 2, '0');
            }
        } elseif ($hasComma) {
            $parts = explode(',', $clean);
            if (count($parts) === 2) {
                // Check if decimal separator (1 or 2 digits) or thousands (3 digits)
                if (strlen($parts[1]) <= 2) {
                    $whole = $parts[0];
                    $fraction = str_pad($parts[1], 2, '0');
                } elseif (strlen($parts[1]) === 3 && strlen($parts[0]) <= 3) {
                    // e.g. 2,500 -> 2500.00
                    $whole = $parts[0].$parts[1];
                    $fraction = '00';
                } else {
                    throw new InvalidArgumentException("Formato monetario ambiguo o inválido: '{$value}'");
                }
            } elseif (count($parts) > 2) {
                // multiple commas e.g. 1,2,3 -> invalid
                throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
            } else {
                $whole = $parts[0];
                $fraction = '00';
            }
        } elseif ($hasDot) {
            $parts = explode('.', $clean);
            if (count($parts) === 2) {
                if (strlen($parts[1]) <= 2) {
                    $whole = $parts[0];
                    $fraction = str_pad($parts[1], 2, '0');
                } elseif (strlen($parts[1]) === 3 && strlen($parts[0]) <= 3) {
                    // e.g. 2.500 thousands
                    $whole = $parts[0].$parts[1];
                    $fraction = '00';
                } else {
                    throw new InvalidArgumentException("Formato monetario ambiguo o inválido: '{$value}'");
                }
            } elseif (count($parts) > 2) {
                throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
            } else {
                $whole = $parts[0];
                $fraction = '00';
            }
        } else {
            $whole = $clean;
            $fraction = '00';
        }

        if (! ctype_digit($whole) || ! ctype_digit($fraction)) {
            throw new InvalidArgumentException("Formato monetario inválido: '{$value}'");
        }

        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        return $isNegative ? -$cents : $cents;
    }

    /**
     * Convert integer cents to a standard decimal string (e.g. 125075 -> "1250.75").
     */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $whole = intdiv($abs, 100);
        $fraction = $abs % 100;

        return sprintf('%s%d.%02d', $sign, $whole, $fraction);
    }

    /**
     * Format integer cents for human display with currency symbol.
     */
    public static function format(int $cents, string $currency = 'RD$'): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $whole = intdiv($abs, 100);
        $fraction = $abs % 100;

        return sprintf('%s%s %s.%02d', $sign, $currency, number_format($whole), $fraction);
    }

    /**
     * Format a decimal or cents amount for display safely.
     */
    public static function formatFromDecimal(mixed $decimal, string $currency = 'RD$'): string
    {
        return self::format(self::toCents($decimal), $currency);
    }
}
