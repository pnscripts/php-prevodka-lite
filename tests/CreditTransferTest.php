<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite\Tests;

use PHPUnit\Framework\TestCase;
use PNScripts\PrevodkaLite\CreditTransfer;
use PNScripts\PrevodkaLite\InvalidFile;
use PNScripts\PrevodkaLite\Payment;
use PNScripts\PrevodkaLite\Problem;

final class CreditTransferTest extends TestCase
{
    private const TODAY = '2026-10-15';

    private function file(): CreditTransfer
    {
        $f = new CreditTransfer('Примерна Фирма ЕООД', 'BG83 IABG 7491 1088 7654 32', 'IABGBGSF');
        $f->executionDate = new \DateTimeImmutable('2026-10-20');
        $f->createdAt = new \DateTimeImmutable('2026-10-15 10:30:00');
        $f->messageId = 'PRV-TEST-0001';
        $f->category = 'SALA';
        $f->add(new Payment('Мария Иванова Петрова', 'BG95 UNCR 7000 1523 4567 89', '1 850,00', 'Заплата за октомври 2026'));
        $f->add(new Payment('Müller Verpackung GmbH', 'DE89370400440532013000', '1,240.00', 'Invoice 2026-118', 'COBADEFFXXX', 'INV-2026-118'));
        $f->add(new Payment('Tipografia Brasov SRL', 'RO30RNCB0082044401920001', 297500, 'Factura 42', '', '', null, 'SUPP'));
        return $f;
    }

    /** @return list<string> */
    private function codes(array $problems): array
    {
        return array_map(static fn (Problem $p) => $p->code, $problems);
    }

    private function assertSchemaValid(string $xml): void
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        libxml_use_internal_errors(true);
        $ok = $doc->schemaValidate(__DIR__ . '/xsd/pain.001.001.09.xsd');
        $errors = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($ok, implode("\n", $errors));
    }

    public function testFileIsValidAgainstTheIso20022Schema(): void
    {
        foreach (['IABGBGSF', ''] as $bic) {
            $f = $this->file();
            $f->debtorBic = $bic;
            $xml = $f->toXml(new \DateTimeImmutable(self::TODAY));
            $this->assertSchemaValid($xml);
        }
        $this->assertStringContainsString('<Id>NOTPROVIDED</Id>', $xml);
        $this->assertStringContainsString('<CtrlSum>6065.00</CtrlSum>', $xml);
        $this->assertStringContainsString('<Nm>Maria Ivanova Petrova</Nm>', $xml);
        $this->assertStringContainsString('<Ustrd>Zaplata za oktomvri 2026</Ustrd>', $xml);
        $this->assertStringContainsString('<Cd>SALA</Cd>', $xml);
        $this->assertStringContainsString('<EndToEndId>INV-2026-118</EndToEndId>', $xml);
        $this->assertStringContainsString('<Dt>2026-10-20</Dt>', $xml);
    }

    public function testControlSumOfManyOddAmountsIsExact(): void
    {
        $f = new CreditTransfer('Payroll Test', 'BG83IABG74911088765432');
        $f->executionDate = new \DateTimeImmutable('2026-10-20');
        mt_srand(13);
        $sum = 0;
        for ($i = 0; $i < 1000; $i++) {
            $cents = mt_rand(1, 500000);
            $sum += $cents;
            $f->add(new Payment("Person $i", 'BG95UNCR70001523456789', sprintf('%d,%02d', intdiv($cents, 100), $cents % 100), "Salary $i"));
        }
        $xml = $f->toXml(new \DateTimeImmutable(self::TODAY));
        $this->assertSchemaValid($xml);
        $this->assertSame($sum, $f->totalCents());
        $this->assertStringContainsString('<CtrlSum>' . intdiv($sum, 100) . '.' . str_pad((string) ($sum % 100), 2, '0', STR_PAD_LEFT) . '</CtrlSum>', $xml);
    }

    public function testErrorsStopTheFileAndWarningsDoNot(): void
    {
        $f = $this->file();
        $f->add(new Payment('Self', 'BG83IABG74911088765432', '12,500', 'Own account'));
        $codes = $this->codes($f->check(new \DateTimeImmutable(self::TODAY)));
        $this->assertContains('self_payment', $codes);
        $this->assertContains('amount_thousands', $codes);
        $this->assertSchemaValid($f->toXml(new \DateTimeImmutable(self::TODAY)));

        $f->add(new Payment('', 'BG95UNCR70001523456788', '12.3456', 'x', 'BADBIC'));
        $f->add(new Payment('Turkish supplier', 'TR330006100519786457841326', '0', ''));
        $codes = $this->codes($f->check(new \DateTimeImmutable(self::TODAY)));
        foreach (['name_empty', 'iban_checksum', 'bic_format', 'amount_invalid', 'iban_country', 'amount_zero'] as $c) {
            $this->assertContains($c, $codes);
        }
        $this->expectException(InvalidFile::class);
        $f->toXml(new \DateTimeImmutable(self::TODAY));
    }

    public function testPastDateAndDuplicateIds(): void
    {
        $f = $this->file();
        $f->executionDate = new \DateTimeImmutable('2026-10-14');
        $f->add(new Payment('Second', 'BG21STSA93000012345678', '5', 'x', '', 'INV-2026-118'));
        $codes = $this->codes($f->check(new \DateTimeImmutable(self::TODAY)));
        $this->assertContains('date_past', $codes);
        $this->assertContains('e2e_duplicate', $codes);
    }

    public function testKeepCyrillicAndBulgarianMessages(): void
    {
        $f = $this->file();
        $f->transliterate = false;
        $xml = $f->toXml(new \DateTimeImmutable(self::TODAY));
        $this->assertStringContainsString('<Nm>Мария Иванова Петрова</Nm>', $xml);
        $this->assertSchemaValid($xml);
        $p = new Problem('iban_checksum', true, 4, 'IBAN', ['value' => 'BG00']);
        $this->assertSame('Грешка (Ред 4, IBAN): IBAN „BG00“ е сгрешен (контролните цифри не съвпадат).', $p->line('bg'));
    }
}
