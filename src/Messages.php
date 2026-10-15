<?php

declare(strict_types=1);

namespace PNScripts\PrevodkaLite;

/** Problem messages in English and Bulgarian. Placeholders: {value}, {max}, {country}, {date}. */
final class Messages
{
    public const TEXTS = [
        'iban_empty' => ['The IBAN is empty.', 'Липсва IBAN.'],
        'iban_chars' => ['The IBAN "{value}" has characters an IBAN cannot have.', 'IBAN „{value}“ съдържа знаци, които не може да има.'],
        'iban_country' => ['The IBAN "{value}" is from a country outside SEPA ({country}).', 'IBAN „{value}“ е от държава извън SEPA ({country}).'],
        'iban_length' => ['The IBAN "{value}" has the wrong length for {country}.', 'IBAN „{value}“ е с грешна дължина за {country}.'],
        'iban_checksum' => ['The IBAN "{value}" has a typing error (its check digits do not match).', 'IBAN „{value}“ е сгрешен (контролните цифри не съвпадат).'],
        'bic_format' => ['The BIC "{value}" is not 8 or 11 letters and digits.', 'BIC „{value}“ не е от 8 или 11 букви и цифри.'],
        'bic_country' => ['The BIC "{value}" is from another country than the IBAN.', 'BIC „{value}“ е от друга държава, а не от тази на IBAN.'],
        'name_empty' => ['The name is empty.', 'Липсва име.'],
        'name_changed' => ['The name was written in SEPA letters as "{value}".', 'Името е записано с латиница за SEPA като „{value}“.'],
        'amount_invalid' => ['The amount "{value}" is not a number with at most two decimals.', 'Сумата „{value}“ не е число с най-много два знака след десетичната запетая.'],
        'amount_zero' => ['The amount must be at least 0.01 EUR.', 'Сумата трябва да е поне 0,01 €.'],
        'amount_max' => ['The amount is more than one SEPA transfer may carry (999 999 999.99 EUR).', 'Сумата е повече, отколкото позволява един SEPA превод (999 999 999,99 €).'],
        'amount_thousands' => ['"{value}" was read as {max}; write it as {max} if that is not what you meant.', '„{value}“ е прочетено като {max}; ако не сте имали това предвид, напишете сумата с десетична запетая.'],
        'self_payment' => ['The payment goes to the account that pays it.', 'Плащането е към сметката, от която се плаща.'],
        'reference_long' => ['The payment reference was cut to 140 characters.', 'Основанието е съкратено до 140 знака.'],
        'reference_changed' => ['The payment reference was written in SEPA letters as "{value}".', 'Основанието е записано с латиница за SEPA като „{value}“.'],
        'e2e_duplicate' => ['The end-to-end id "{value}" is used twice in this file.', 'Идентификаторът „{value}“ е използван два пъти в този файл.'],
        'date_past' => ['The execution date {date} is in the past.', 'Датата на изпълнение {date} е минала.'],
        'debtor_iban' => ['Your own IBAN (the account paying) is not valid: {value}.', 'IBAN на платеца (вашата сметка) не е валиден: {value}.'],
        'debtor_name' => ['The name of the account paying is empty.', 'Липсва името на платеца.'],
        'no_payments' => ['There are no payments to put in the file.', 'Няма плащания за файла.'],
        'too_many' => ['A file holds at most {max} payments; split the list.', 'Файлът побира най-много {max} плащания; разделете списъка.'],
    ];

    /** @param array<string, string> $vars */
    public static function text(string $code, string $lang = 'en', array $vars = []): string
    {
        $pair = self::TEXTS[$code] ?? [$code, $code];
        $t = $pair[$lang === 'bg' ? 1 : 0];
        foreach ($vars as $k => $v) {
            $t = str_replace('{' . $k . '}', $v, $t);
        }
        return $t;
    }
}
