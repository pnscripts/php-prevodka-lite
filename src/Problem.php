<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/** One thing found while checking: an error stops the file, a warning is shown and the file is still made. */
final class Problem
{
    private const FIELDS_BG = ['date' => 'дата', 'amount' => 'сума', 'reference' => 'основание', 'name' => 'име', 'debtor' => 'платец',
        'debtor BIC' => 'BIC на платеца', 'id' => 'идентификатор'];

    /** @param array<string, string> $vars */
    public function __construct(
        public readonly string $code,
        public readonly bool $error,
        public readonly ?int $row = null,
        public readonly string $field = '',
        public readonly array $vars = [],
    ) {
    }

    public function message(string $lang = 'en'): string
    {
        return Messages::text($this->code, $lang, $this->vars);
    }

    /** "Row 4, IBAN: ..." in the chosen language. */
    public function line(string $lang = 'en'): string
    {
        $where = [];
        if ($this->row !== null) {
            $where[] = ($lang === 'bg' ? 'Ред ' : 'Row ') . $this->row;
        }
        if ($this->field !== '') {
            $where[] = $lang === 'bg' ? (self::FIELDS_BG[$this->field] ?? $this->field) : $this->field;
        }
        $kind = $this->error ? ($lang === 'bg' ? 'Грешка' : 'Error') : ($lang === 'bg' ? 'Внимание' : 'Warning');
        return $kind . ($where ? ' (' . implode(', ', $where) . ')' : '') . ': ' . $this->message($lang);
    }
}
