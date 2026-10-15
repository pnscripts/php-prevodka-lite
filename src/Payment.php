<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/** One credit transfer: who gets the money, to which IBAN, how much (in cents) and why. */
final class Payment
{
    public function __construct(
        public string $name,
        public string $iban,
        public int|string $amount,
        public string $reference = '',
        public string $bic = '',
        public string $endToEndId = '',
        public ?int $row = null,
        /** ISO 20022 purpose code for this payment only, e.g. SALA, PENS, SUPP; blank uses the file's category. */
        public string $purpose = '',
    ) {
    }

    /** Cents, or null when the amount text cannot be read. */
    public function cents(): ?int
    {
        return is_int($this->amount) ? $this->amount : Amount::parse($this->amount);
    }
}
