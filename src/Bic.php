<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/** BIC (SWIFT code) format check: 4 letters bank, 2 letters country, 2 location, optional 3 branch. */
final class Bic
{
    public static function normalize(string $bic): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $bic));
    }

    public static function isValid(string $bic): bool
    {
        return (bool) preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', self::normalize($bic));
    }

    /** The BIC's country must match the IBAN's (Monaco and Italy, San Marino and Italy aside, banks keep their own). */
    public static function country(string $bic): string
    {
        return substr(self::normalize($bic), 4, 2);
    }
}
