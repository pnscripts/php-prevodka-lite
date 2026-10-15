<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite\Tests;

use PHPUnit\Framework\TestCase;
use PNScripts\PrevodkaLite\Amount;
use PNScripts\PrevodkaLite\Bic;
use PNScripts\PrevodkaLite\Iban;
use PNScripts\PrevodkaLite\Text;

final class PartsTest extends TestCase
{
    public function testIbanExamplesFromTheSwiftRegistry(): void
    {
        // Example IBANs published in the SWIFT IBAN registry.
        foreach (['BG80BNBG96611020345678', 'DE89370400440532013000', 'GR1601101250000000012300695', 'RO49AAAA1B31007593840000',
            'AT611904300234573201', 'BE68539007547034', 'CY17002001280000001200527600', 'CZ6508000000192000145399', 'DK5000400440116243',
            'EE382200221020145685', 'ES9121000418450200051332', 'FI2112345600000785', 'FR1420041010050500013M02606', 'HR1210010051863000160',
            'HU42117730161111101800000000', 'IE29AIBK93115212345678', 'IT60X0542811101000000123456', 'LT121000011101001000',
            'LU280019400644750000', 'LV80BANK0000435195001', 'MT84MALT011000012345MTLCAST001S', 'NL91ABNA0417164300', 'PL61109010140000071219812874',
            'PT50000201231234567890154', 'SE4550000000058398257466', 'SI56263300012039086', 'SK3112000000198742637541', 'CH9300762011623852957',
            'GB29NWBK60161331926819', 'NO9386011117947', 'IS140159260076545510730339', 'LI21088100002324013AA', 'MC5811222000010123456789030',
            'SM86U0322509800000000270100', 'AD1200012030200359100100', 'VA59001123000012345678', 'GI75NWBK000000007099453',
            'AL47212110090000000235698741', 'MD24AG000225100013104168', 'ME25505000012345678951', 'MK07250120000058984'] as $iban) {
            $this->assertNull(Iban::problem($iban), $iban);
        }
        $this->assertSame('BG80 BNBG 9661 1020 3456 78', Iban::format(' bg80-bnbg.9661 1020 3456 78 '));
    }

    public function testIbanProblems(): void
    {
        $this->assertSame('iban_checksum', Iban::problem('BG80BNBG96611020345679'));
        $this->assertSame('iban_checksum', Iban::problem('BG80BNBG96611020345687'), 'two swapped digits');
        $this->assertSame('iban_length', Iban::problem('BG80BNBG9661102034567'));
        $this->assertSame('iban_country', Iban::problem('US64SVBKUS6S3300958879'));
        $this->assertSame('iban_country', Iban::problem('TR330006100519786457841326'), 'Turkey is not in SEPA');
        $this->assertSame('iban_chars', Iban::problem('BG80BNBG9661102034567$'));
        $this->assertSame('iban_empty', Iban::problem('  '));
    }

    public function testBic(): void
    {
        $this->assertTrue(Bic::isValid('UNCRBGSF'));
        $this->assertTrue(Bic::isValid('cobadeffxxx'));
        $this->assertFalse(Bic::isValid('UNCRBGS'));
        $this->assertFalse(Bic::isValid('UNCR1GSF'));
    }

    public function testAmounts(): void
    {
        $cases = ['1234.56' => 123456, '1 234,56' => 123456, '1.234,56' => 123456, '1,234.56' => 123456, "1\u{00A0}234,56" => 123456,
            '12,5' => 1250, '0,01' => 1, '1 500 €' => 150000, '1500 EUR' => 150000, '1,234,567' => 123456700, '7' => 700,
            '1234.567' => null, 'abc' => null, '12,3,4' => null, '' => null, '1.2.3' => null];
        foreach ($cases as $text => $cents) {
            $this->assertSame($cents, Amount::parse((string) $text), (string) $text);
        }
        $this->assertSame(1999, Amount::parse(19.99));
        $this->assertSame('1234.56', Amount::format(123456));
        $this->assertSame('0.05', Amount::format(5));
        $this->assertSame("1\u{00A0}234,56\u{00A0}€", Amount::display(123456, 'bg'));
        $this->assertSame('1,234.56 EUR', Amount::display(123456));
    }

    public function testBulgarianTransliterationAct(): void
    {
        // Examples in the Transliteration Act (2009) and its rules: щ sht, ъ a, ь y, ю yu, я ya, -ия at the end ia.
        $words = ['София' => 'Sofia', 'Пловдив' => 'Plovdiv', 'Стара Загора' => 'Stara Zagora', 'Шумен' => 'Shumen', 'Ямбол' => 'Yambol',
            'Щерев' => 'Shterev', 'Цвета' => 'Tsveta', 'Йордан' => 'Yordan', 'Юлия' => 'Yulia', 'Мария' => 'Maria', 'Илия' => 'Ilia',
            'Българска' => 'Balgarska', 'България' => 'Bulgaria', 'Жечка' => 'Zhechka', 'Христо' => 'Hristo', 'Ючбунар' => 'Yuchbunar',
            'Тетевен' => 'Teteven', 'Кьосев' => 'Kyosev', 'ЩЕРЕВ И СИЕ ООД' => 'SHTEREV I SIE OOD', 'Ия' => 'Ia', 'Яхния' => 'Yahnia'];
        foreach ($words as $bg => $latin) {
            $this->assertSame($latin, Text::transliterate($bg), $bg);
        }
        $this->assertSame('Iliyan', Text::transliterate('Илиян'), '-ия inside a word stays iy');
    }

    public function testSepaCharacterSet(): void
    {
        $this->assertSame("Zaplata za m. 10/2026 - 'Yuli' + bonus", Text::sepa('Заплата за м. 10/2026 – „Юли“ & бонус'));
        $this->assertSame('Muller Stastny Lodz', Text::sepa('Müller Šťastný Łódź'));
        $this->assertSame('Fakt. No 12', Text::sepa('Факт. № 12'));
        $this->assertSame('Mihaela Ivanova', Text::sepa("Михаела\tИванова\n"));
        $this->assertSame(10, strlen(Text::sepa('abcdefghijklmnop', 10)));
        $this->assertTrue(Text::isSepa(Text::sepa('Ще платим 100% на <Иван> @ офиса {днес}; #3')));
        $this->assertSame('Йордан Щерев, фактура No 7 - Muller', Text::sepa('Йордан Щерев, фактура № 7 – Müller', 70, false), 'Cyrillic kept on request');
        $this->assertSame('ab/c-d', Text::id('//ab//c d//'));
        $this->assertSame(35, strlen(Text::id(str_repeat('x', 50))));
    }
}
