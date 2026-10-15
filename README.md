# Prevodka Lite

Free PHP library by [PN Scripts](https://pnscripts.com) that writes SEPA credit transfer files
(ISO 20022 **pain.001.001.09**) for bulk payments in euro, after checking every IBAN, BIC and amount.
Names and references in Cyrillic are written in Latin letters by the Bulgarian Transliteration Act,
other characters outside the SEPA set are replaced, and totals are counted in whole cents so the
control sums always match. No dependencies beyond PHP's dom and mbstring.

```
composer require pnscripts/prevodka-lite
```

```php
use PNScripts\PrevodkaLite\{CreditTransfer, Payment};

$file = new CreditTransfer('Примерна Фирма ЕООД', 'BG83 IABG 7491 1088 7654 32');   // BIC optional
$file->executionDate = new DateTimeImmutable('2026-10-30');
$file->category = 'SALA';                                                          // salaries; or SUPP, PENS, ...
$file->add(new Payment('Мария Петрова', 'BG95 UNCR 7000 1523 4567 89', '1 850,00', 'Заплата за октомври 2026'));
$file->add(new Payment('Müller Verpackung GmbH', 'DE89 3704 0044 0532 0130 00', 124000, 'Invoice 2026-118', 'COBADEFFXXX'));

foreach ($file->check() as $problem) {
    echo $problem->line('bg'), "\n";      // errors and warnings, Bulgarian or English
}
file_put_contents('payments.xml', $file->toXml());   // throws InvalidFile while there are errors
```

The file goes into your online bank where it accepts payment files (bulk or batch payments). Compare
the number of payments and the total the bank shows before you sign.

## What it checks

- IBAN: country in SEPA (41 countries), length for that country, ISO 7064 check digits.
- BIC: 8 or 11 characters, and a warning when its country differs from the IBAN's.
- Amount: text such as `1850`, `1 850,00`, `1.850,00`, `1,850.00` or integer cents; at least 0.01,
  at most 999 999 999.99 EUR, at most two decimals; a warning for `12,500`-style amounts read as
  thousands.
- Name present; reference longer than 140 characters (cut) or changed for the SEPA character set.
- End-to-end ids used twice; execution date in the past; a payment to the paying account.

`Iban`, `Bic`, `Amount` and `Text::sepa()` / `Text::transliterate()` work on their own.

## Lite and full edition

Prevodka Lite is complete for building files in PHP code. The full edition,
[Prevodka](https://pnscripts.com/marketplace/php-prevodka), adds reading lists from Excel and CSV
(Bulgarian headings, Windows-1251), a command line tool and a local web page, pain.001.001.03, SEPA
Instant with limits, RF creditor references, TARGET closing days, duplicate and same-IBAN warnings, and
a reader that checks any pain.001 file.

## Limits

Euro credit transfers only (no direct debits, other currencies or payments to the Bulgarian budget).
Not tried with a real bank during development: the output is validated against the ISO 20022 schema
in the tests, but each bank has its own limits, so send a small test file first. A valid IBAN can still
belong to the wrong person.

## Tests

```
composer install
vendor/bin/phpunit     # 11 tests; every file is validated against the ISO 20022 pain.001.001.09 schema
```

The schema in `tests/xsd` is the ISO 20022 pain.001.001.09 message schema, copied from the test
fixtures of the MIT-licensed php-sepa-xml project, and is used by the tests only.

MIT licence. Made by PN Scripts (https://pnscripts.com, office@pnscripts.com). Created with AI
assistance and checked by PN Scripts with automated tests.
