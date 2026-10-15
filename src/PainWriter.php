<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/** Writes a checked CreditTransfer as pain.001.001.09 with the SEPA rules applied. */
final class PainWriter
{
    private \DOMDocument $doc;

    public function __construct(private readonly CreditTransfer $file)
    {
    }

    public function xml(): string
    {
        $f = $this->file;
        $ns = 'urn:iso:std:iso:20022:tech:xsd:' . CreditTransfer::VERSION;
        $this->doc = new \DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $root = $this->doc->createElementNS($ns, 'Document');
        $this->doc->appendChild($root);
        $init = $this->el($root, 'CstmrCdtTrfInitn');

        $total = 0;
        foreach ($f->payments() as $p) {
            $total += (int) $p->cents();
        }
        $created = $f->created();

        $hdr = $this->el($init, 'GrpHdr');
        $this->el($hdr, 'MsgId', $f->messageId());
        $this->el($hdr, 'CreDtTm', $created->format('Y-m-d\TH:i:s'));
        $this->el($hdr, 'NbOfTxs', (string) $f->count());
        $this->el($hdr, 'CtrlSum', Amount::format($total));
        $party = $this->el($hdr, 'InitgPty');
        $this->el($party, 'Nm', Text::sepa($f->initiatingParty !== '' ? $f->initiatingParty : $f->debtorName, 70, $f->transliterate));

        $inf = $this->el($init, 'PmtInf');
        $this->el($inf, 'PmtInfId', Text::id($f->messageId() . '-1'));
        $this->el($inf, 'PmtMtd', 'TRF');
        $this->el($inf, 'BtchBookg', $f->batchBooking ? 'true' : 'false');
        $this->el($inf, 'NbOfTxs', (string) $f->count());
        $this->el($inf, 'CtrlSum', Amount::format($total));
        $type = $this->el($inf, 'PmtTpInf');
        $this->el($this->el($type, 'SvcLvl'), 'Cd', 'SEPA');
        if ($f->category) {
            $this->el($this->el($type, 'CtgyPurp'), 'Cd', $f->category);
        }
        $this->el($this->el($inf, 'ReqdExctnDt'), 'Dt', $f->executionDate->format('Y-m-d'));
        $this->el($this->el($inf, 'Dbtr'), 'Nm', Text::sepa($f->debtorName, 70, $f->transliterate));
        $this->el($this->el($this->el($inf, 'DbtrAcct'), 'Id'), 'IBAN', Iban::normalize($f->debtorIban));
        $this->agent($inf, 'DbtrAgt', $f->debtorBic, true);
        $this->el($inf, 'ChrgBr', 'SLEV');

        foreach ($f->payments() as $i => $p) {
            $tx = $this->el($inf, 'CdtTrfTxInf');
            $this->el($this->el($tx, 'PmtId'), 'EndToEndId', $f->endToEnd($p, $i));
            $amt = $this->el($this->el($tx, 'Amt'), 'InstdAmt', Amount::format((int) $p->cents()));
            $amt->setAttribute('Ccy', 'EUR');
            if ($p->bic !== '') {
                $this->agent($tx, 'CdtrAgt', $p->bic, false);
            }
            $this->el($this->el($tx, 'Cdtr'), 'Nm', Text::sepa($p->name, 70, $f->transliterate));
            $this->el($this->el($this->el($tx, 'CdtrAcct'), 'Id'), 'IBAN', Iban::normalize($p->iban));
            if ($p->purpose !== '') {
                $this->el($this->el($tx, 'Purp'), 'Cd', strtoupper($p->purpose));
            }
            $ref = trim($p->reference);
            if ($ref !== '') {
                $this->el($this->el($tx, 'RmtInf'), 'Ustrd', Text::sepa($ref, 140, $f->transliterate));
            }
        }
        return (string) $this->doc->saveXML();
    }

    private function agent(\DOMElement $parent, string $name, string $bic, bool $required): void
    {
        if ($bic === '' && !$required) {
            return;
        }
        $fin = $this->el($this->el($parent, $name), 'FinInstnId');
        if ($bic !== '') {
            $this->el($fin, 'BICFI', Bic::normalize($bic));
        } else {
            // The SEPA rulebook makes the BIC optional; this is how the guidelines say "not provided".
            $this->el($this->el($fin, 'Othr'), 'Id', 'NOTPROVIDED');
        }
    }

    private function el(\DOMNode $parent, string $name, ?string $text = null): \DOMElement
    {
        $ns = $parent instanceof \DOMDocument ? null : $parent->namespaceURI;
        $el = $this->doc->createElementNS((string) ($ns ?? ''), $name);
        if ($text !== null) {
            $el->appendChild($this->doc->createTextNode($text));
        }
        $parent->appendChild($el);
        return $el;
    }
}
