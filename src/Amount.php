<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/**
 * Euro amounts as whole cents, so totals never pick up floating point errors.
 * Reads 1234.56, 1234,56, 1 234,56, 1.234,56, 1,234.56 and "1234.56 EUR" / "1234,56 €" / "лв."-free text.
 */
final class Amount
{
    /** Largest amount one SEPA transfer may carry: 999 999 999.99 EUR. */
    public const MAX_CENTS = 99_999_999_999;

    /** Cents, or null when the text is not an amount with at most two decimals. */
    public static function parse(string|int|float $value): ?int
    {
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }
        $s = trim((string) preg_replace('/(EUR|€|евро|euro)/iu', '', $value));
        $s = (string) preg_replace("/[\\s\\x{00A0}\\x{202F}']+/u", '', $s);
        if ($s === '' || !preg_match('/^-?[0-9][0-9.,]*$/', $s)) {
            return null;
        }
        $negative = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        $sep = null;
        if ($lastDot !== false && $lastComma !== false) {
            $sep = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastDot !== false || $lastComma !== false) {
            $one = $lastDot !== false ? '.' : ',';
            $pos = (int) ($lastDot !== false ? $lastDot : $lastComma);
            $decimals = strlen($s) - $pos - 1;
            // 1.234 or 1,234,567: thousands; 12.5, 12,50: decimals.
            $sep = (substr_count($s, $one) === 1 && $decimals !== 3) ? $one : null;
            if (substr_count($s, $one) > 1 || $decimals === 3) {
                if (!preg_match('/^[0-9]{1,3}(\\' . $one . '[0-9]{3})+$/', $s)) {
                    return null;
                }
            }
        }
        if ($sep !== null) {
            $int = substr($s, 0, (int) strrpos($s, $sep));
            $frac = substr($s, (int) strrpos($s, $sep) + 1);
            $int = str_replace(['.', ','], '', $int);
        } else {
            $int = str_replace(['.', ','], '', $s);
            $frac = '';
        }
        if ($int === '' || !ctype_digit($int) || ($frac !== '' && !ctype_digit($frac)) || strlen($frac) > 2) {
            return null;
        }
        $cents = (int) $int * 100 + (int) str_pad($frac, 2, '0');
        return $negative ? -$cents : $cents;
    }

    /**
     * True for amounts like 1.234 or 12,500 that are read as thousands (1 234.00, 12 500.00) but might
     * have been meant as decimals; the checker warns about them.
     */
    public static function isAmbiguous(string|int|float $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $s = (string) preg_replace("/[\\s\\x{00A0}\\x{202F}€]|EUR/iu", '', $value);
        return (bool) preg_match('/^-?[0-9]{1,3}[.,][0-9]{3}$/', $s);
    }

    /** 123456 -> "1234.56", the format pain.001 needs. */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 123456 -> "1 234,56" (Bulgarian) or "1,234.56" (English), for people to read. */
    public static function display(int $cents, string $lang = 'en'): string
    {
        $n = number_format($cents / 100, 2, $lang === 'bg' ? ',' : '.', $lang === 'bg' ? "\u{00A0}" : ',');
        return $n . ($lang === 'bg' ? "\u{00A0}€" : ' EUR');
    }
}
