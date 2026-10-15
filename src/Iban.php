<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/**
 * IBAN checks for the SEPA area: country code, length per country and the ISO 7064 mod 97-10 check digits.
 */
final class Iban
{
    /**
     * IBAN lengths of the countries in the SEPA schemes: EU and EEA, Switzerland, the UK, Gibraltar, Monaco,
     * San Marino, Andorra, the Vatican, and Albania, Moldova, Montenegro and North Macedonia (joined 2025).
     * Overseas territories, the Channel Islands and the Isle of Man use FR, FI or GB IBANs.
     */
    public const SEPA_LENGTHS = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24, 'DE' => 22,
        'DK' => 18, 'EE' => 20, 'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27,
        'HR' => 21, 'HU' => 28, 'IE' => 22, 'IS' => 26, 'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20,
        'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28, 'PT' => 25, 'RO' => 24,
        'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'VA' => 22, 'AL' => 28, 'MD' => 24, 'ME' => 22,
        'MK' => 19,
    ];

    /** Removes spaces, dashes and dots and upper-cases the letters. */
    public static function normalize(string $iban): string
    {
        return strtoupper((string) preg_replace('/[\s\x{00A0}.\-]+/u', '', $iban));
    }

    /** Groups of four, the way IBANs are printed: BG80 BNBG 9661 1020 3456 78. */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    /**
     * Returns null when the IBAN is valid for SEPA, otherwise the reason as a message code
     * (iban_empty, iban_chars, iban_country, iban_length, iban_checksum).
     */
    public static function problem(string $iban): ?string
    {
        $iban = self::normalize($iban);
        if ($iban === '') {
            return 'iban_empty';
        }
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return 'iban_chars';
        }
        $country = substr($iban, 0, 2);
        if (!isset(self::SEPA_LENGTHS[$country])) {
            return 'iban_country';
        }
        if (strlen($iban) !== self::SEPA_LENGTHS[$country]) {
            return 'iban_length';
        }
        return self::mod97(substr($iban, 4) . substr($iban, 0, 4)) === 1 ? null : 'iban_checksum';
    }

    public static function isValid(string $iban): bool
    {
        return self::problem($iban) === null;
    }

    public static function country(string $iban): string
    {
        return substr(self::normalize($iban), 0, 2);
    }

    /** ISO 7064 mod 97-10 over letters turned into numbers (A = 10 ... Z = 35), in chunks so no big integers are needed. */
    public static function mod97(string $alnum): int
    {
        $digits = '';
        foreach (str_split($alnum) as $c) {
            $digits .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }
        $rest = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $rest = (int) ($rest . $chunk) % 97;
        }
        return $rest;
    }
}
