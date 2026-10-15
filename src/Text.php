<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/**
 * Text for SEPA files. The SEPA schemes only promise the basic Latin set
 * (a-z A-Z 0-9 / - ? : ( ) . , ' + and space), so names and references in Cyrillic are
 * transliterated by the Bulgarian Transliteration Act (2009) and accented letters lose their accents.
 */
final class Text
{
    private const BG = [
        'щ' => 'sht', 'ж' => 'zh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'ю' => 'yu', 'я' => 'ya',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ъ' => 'a', 'ь' => 'y',
        // Letters of Russian, Ukrainian and Serbian/Macedonian names, written the usual way.
        'ё' => 'yo', 'ы' => 'y', 'э' => 'e', 'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
        'ђ' => 'dj', 'ј' => 'j', 'љ' => 'lj', 'њ' => 'nj', 'ћ' => 'c', 'џ' => 'dz', 'ѓ' => 'gj', 'ќ' => 'kj', 'ѕ' => 'dz',
    ];

    private const LETTERS = [
        'ß' => 'ss', 'æ' => 'ae', 'Æ' => 'AE', 'ø' => 'o', 'Ø' => 'O', 'œ' => 'oe', 'Œ' => 'OE', 'đ' => 'd', 'Đ' => 'D',
        'ł' => 'l', 'Ł' => 'L', 'þ' => 'th', 'Þ' => 'TH', 'ð' => 'd', 'Ð' => 'D', 'ı' => 'i',
    ];

    private const SYMBOLS = [
        '&' => '+', '„' => "'", '“' => "'", '”' => "'", '‘' => "'", '’' => "'", '"' => "'", '«' => "'", '»' => "'",
        '–' => '-', '—' => '-', '_' => '-', '№' => 'No', '#' => 'No', ';' => ',', '!' => '.', '*' => '.', '=' => '-',
        '[' => '(', ']' => ')', '{' => '(', '}' => ')', '\\' => '/', '|' => '/', '%' => ' pct', '€' => 'EUR',
        '@' => '(at)',
    ];

    /** Bulgarian Cyrillic to Latin by the Transliteration Act: "-ия" at the end of a word is "-ia". */
    public static function transliterate(string $text): string
    {
        // The Act's one exception: the name of the country.
        $text = (string) preg_replace(['/(?<!\p{L})България(?!\p{L})/u', '/(?<!\p{L})БЪЛГАРИЯ(?!\p{L})/u'], ['Bulgaria', 'BULGARIA'], $text);
        $text = (string) preg_replace_callback('/ия(?=$|[^\p{L}])/u', static fn () => 'ia', $text);
        $text = (string) preg_replace_callback('/ИЯ(?=$|[^\p{L}])/u', static fn () => 'IA', $text);
        $text = (string) preg_replace_callback('/Ия(?=$|[^\p{L}])/u', static fn () => 'Ia', $text);
        return (string) preg_replace_callback('/\p{Cyrillic}/u', static function (array $m): string {
            $c = $m[0];
            $lower = mb_strtolower($c, 'UTF-8');
            $latin = self::BG[$lower] ?? '';
            if ($c === $lower || $latin === '') {
                return $latin;
            }
            // Capital letter: first letter capital, or all capitals when the word is in capitals.
            return ucfirst($latin);
        }, self::capitals($text));
    }

    /** Words written in capitals keep all their Latin letters in capitals (ЩЕРЕВ -> SHTEREV, not ShTEREV). */
    private static function capitals(string $text): string
    {
        return (string) preg_replace_callback('/(?<!\p{L})\p{Lu}{2,}(?!\p{L})/u', static function (array $m): string {
            $out = '';
            foreach (mb_str_split($m[0]) as $c) {
                $l = mb_strtolower($c, 'UTF-8');
                $out .= isset(self::BG[$l]) ? strtoupper(self::BG[$l]) : $c;
            }
            return $out;
        }, $text);
    }

    /** Strips accents from Latin letters: Müller -> Muller, Šťastný -> Stastny. */
    public static function stripAccents(string $text): string
    {
        $text = strtr($text, self::LETTERS);
        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_D);
            return (string) preg_replace('/\p{Mn}+/u', '', $text);
        }
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $converted === false ? $text : $converted;
    }

    /** Accents off and symbols outside the SEPA set replaced by their nearest SEPA characters (& +, „“ '). */
    public static function asciiLatin(string $text): string
    {
        return self::stripAccents(strtr($text, self::SYMBOLS));
    }

    /**
     * Text the way a SEPA file may carry it: transliterated, accents removed, other characters
     * replaced or dropped, spaces collapsed, cut to $max characters.
     */
    public static function sepa(string $text, int $max = 140, bool $transliterate = true): string
    {
        $text = (string) preg_replace('/[\r\n\t\x{00A0}]+/u', ' ', $text);
        if ($transliterate) {
            $text = self::asciiLatin(self::transliterate($text));
            $text = (string) preg_replace("~[^A-Za-z0-9/\\-?:().,'+ ]~", '', $text);
        } else {
            // Cyrillic kept as it is (only for banks that accept it); everything else as above.
            $text = (string) preg_replace_callback('/[^\p{Cyrillic}]+/u', static fn (array $m) => self::asciiLatin($m[0]), $text);
            $text = (string) preg_replace("~[^A-Za-z0-9/\\-?:().,'+ \p{Cyrillic}]~u", '', $text);
        }
        $text = trim((string) preg_replace('/ {2,}/', ' ', $text));
        return rtrim(mb_substr($text, 0, $max));
    }

    /** True when the text uses only the SEPA basic Latin set. */
    public static function isSepa(string $text): bool
    {
        return (bool) preg_match("~^[A-Za-z0-9/\\-?:().,'+ ]*$~", $text);
    }

    /**
     * Identifiers (message, batch and end-to-end ids): basic Latin set, no leading or trailing slash
     * and no double slash, as the EPC implementation guidelines require.
     */
    public static function id(string $text, int $max = 35): string
    {
        $text = self::sepa($text, 200);
        $text = str_replace(' ', '-', $text);
        $text = (string) preg_replace('~/{2,}~', '/', $text);
        $text = trim($text, '/');
        return trim(substr($text, 0, $max), '/');
    }
}
