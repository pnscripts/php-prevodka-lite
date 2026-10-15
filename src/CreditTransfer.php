<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/**
 * A SEPA credit transfer file (ISO 20022 pain.001.001.09): one paying account, one execution date,
 * any number of payments. check() lists what is wrong; toXml() writes the file the bank takes.
 */
final class CreditTransfer
{
    public const VERSION = 'pain.001.001.09';
    public const MAX_PAYMENTS = 100_000;

    /** @var list<Payment> */
    private array $payments = [];

    public \DateTimeImmutable $executionDate;
    /** Category purpose of the whole batch: SALA (salaries), PENS, SUPP (suppliers), TAXS, ... or null. */
    public ?string $category = null;
    /** One debit on the statement for the whole batch (true) or one per payment (false). */
    public bool $batchBooking = true;
    /** Write Cyrillic names and references in Latin letters (the SEPA basic set). */
    public bool $transliterate = true;
    public string $messageId = '';
    public string $initiatingParty = '';
    public ?\DateTimeImmutable $createdAt = null;

    public function __construct(
        public string $debtorName,
        public string $debtorIban,
        public string $debtorBic = '',
    ) {
        $this->executionDate = (new \DateTimeImmutable('today'));
    }

    public function add(Payment $payment): self
    {
        $this->payments[] = $payment;
        return $this;
    }

    /** @param iterable<Payment> $payments */
    public function addAll(iterable $payments): self
    {
        foreach ($payments as $p) {
            $this->add($p);
        }
        return $this;
    }

    /** @return list<Payment> */
    public function payments(): array
    {
        return $this->payments;
    }

    public function count(): int
    {
        return count($this->payments);
    }

    /** Sum of all payments with a readable amount, in cents. */
    public function totalCents(): int
    {
        $sum = 0;
        foreach ($this->payments as $p) {
            $sum += max(0, (int) $p->cents());
        }
        return $sum;
    }

    /** @return list<Problem> errors and warnings, in row order */
    public function check(?\DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $out = [];
        if (trim($this->debtorName) === '') {
            $out[] = new Problem('debtor_name', true, null, 'debtor');
        }
        if (($code = Iban::problem($this->debtorIban)) !== null) {
            $out[] = new Problem('debtor_iban', true, null, 'debtor', ['value' => $this->debtorIban]);
        }
        if ($this->debtorBic !== '' && !Bic::isValid($this->debtorBic)) {
            $out[] = new Problem('bic_format', true, null, 'debtor BIC', ['value' => $this->debtorBic]);
        }
        $date = $this->executionDate->setTime(0, 0);
        if ($date < $today) {
            $out[] = new Problem('date_past', true, null, 'date', ['date' => $date->format('Y-m-d')]);
        }
        if (!$this->payments) {
            $out[] = new Problem('no_payments', true);
        }
        if (count($this->payments) > self::MAX_PAYMENTS) {
            $out[] = new Problem('too_many', true, null, '', ['max' => (string) self::MAX_PAYMENTS]);
        }
        $ids = [];
        foreach ($this->payments as $i => $p) {
            $row = $p->row ?? $i + 1;
            foreach ($this->checkPayment($p, $row) as $problem) {
                $out[] = $problem;
            }
            $e2e = $this->endToEnd($p, $i);
            if (isset($ids[$e2e]) && $p->endToEndId !== '') {
                $out[] = new Problem('e2e_duplicate', true, $row, 'id', ['value' => $e2e]);
            }
            $ids[$e2e] = true;
        }
        return $out;
    }

    /** @return list<Problem> */
    private function checkPayment(Payment $p, int $row): array
    {
        $out = [];
        if (trim($p->name) === '') {
            $out[] = new Problem('name_empty', true, $row, 'name');
        } else {
            $sepa = Text::sepa($p->name, 70, $this->transliterate);
            if ($sepa === '') {
                $out[] = new Problem('name_empty', true, $row, 'name');
            } elseif (!self::onlyTransliterated($p->name, $sepa)) {
                $out[] = new Problem('name_changed', false, $row, 'name', ['value' => $sepa]);
            }
        }
        $code = Iban::problem($p->iban);
        if ($code !== null) {
            $out[] = new Problem($code, true, $row, 'IBAN', ['value' => $p->iban, 'country' => Iban::country($p->iban)]);
        } elseif (Iban::normalize($p->iban) === Iban::normalize($this->debtorIban)) {
            $out[] = new Problem('self_payment', false, $row, 'IBAN');
        }
        if ($p->bic !== '') {
            if (!Bic::isValid($p->bic)) {
                $out[] = new Problem('bic_format', true, $row, 'BIC', ['value' => $p->bic]);
            } elseif ($code === null && !self::bicMatches(Bic::country($p->bic), Iban::country($p->iban))) {
                $out[] = new Problem('bic_country', false, $row, 'BIC', ['value' => $p->bic]);
            }
        }
        $cents = $p->cents();
        if ($cents === null) {
            $out[] = new Problem('amount_invalid', true, $row, 'amount', ['value' => (string) $p->amount]);
        } elseif ($cents < 1) {
            $out[] = new Problem('amount_zero', true, $row, 'amount');
        } elseif ($cents > Amount::MAX_CENTS) {
            $out[] = new Problem('amount_max', true, $row, 'amount');
        }
        if ($cents !== null && Amount::isAmbiguous($p->amount)) {
            $out[] = new Problem('amount_thousands', false, $row, 'amount', ['value' => (string) $p->amount, 'max' => Amount::format($cents)]);
        }
        $ref = trim($p->reference);
        if ($ref !== '') {
            $sepa = Text::sepa($ref, 1000, $this->transliterate);
            if (mb_strlen($sepa) > 140) {
                $out[] = new Problem('reference_long', false, $row, 'reference');
            } elseif (!self::onlyTransliterated($ref, $sepa)) {
                $out[] = new Problem('reference_changed', false, $row, 'reference', ['value' => $sepa]);
            }
        }
        return $out;
    }

    /**
     * True when the SEPA text differs from the original only by transliteration and accents, which the
     * check does not need to point out on every row; dropped or replaced characters are pointed out.
     */
    private static function onlyTransliterated(string $original, string $sepa): bool
    {
        $plain = trim((string) preg_replace('/\s+/', ' ', Text::stripAccents(Text::transliterate(trim($original)))));
        return $plain === $sepa;
    }

    /** Monaco and San Marino accounts may sit with French or Italian banks; Gibraltar with UK ones. */
    private static function bicMatches(string $bicCountry, string $ibanCountry): bool
    {
        if ($bicCountry === $ibanCountry) {
            return true;
        }
        $pairs = ['MC' => ['FR'], 'SM' => ['IT'], 'VA' => ['IT'], 'GI' => ['GB'], 'LI' => ['CH'], 'AD' => ['ES', 'FR']];
        return in_array($bicCountry, $pairs[$ibanCountry] ?? [], true);
    }

    public function endToEnd(Payment $p, int $index): string
    {
        $id = Text::id($p->endToEndId);
        return $id !== '' ? $id : Text::id($this->messageId() . '-' . ($index + 1));
    }

    public function messageId(): string
    {
        if ($this->messageId === '') {
            $this->messageId = 'PRV' . $this->created()->format('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
        }
        return Text::id($this->messageId);
    }

    /** When the file was made, in Sofia time unless set. */
    public function created(): \DateTimeImmutable
    {
        return $this->createdAt ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Sofia'));
    }

    /** The pain.001.001.09 XML. Throws InvalidFile when check() finds errors. */
    public function toXml(?\DateTimeImmutable $today = null): string
    {
        $problems = $this->check($today);
        foreach ($problems as $p) {
            if ($p->error) {
                throw new InvalidFile($problems);
            }
        }
        return (new PainWriter($this))->xml();
    }
}
