<?php

namespace Tests\Unit;

use App\Support\CompanyName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CompanyNameTest extends TestCase
{
    /** Mintrans'dan kelgan haqiqiy nomlar (mintrans_result namunasidan) */
    public static function names(): array
    {
        return [
            'МЧЖ + MCHJ iborasi' => ['МЧЖ "ANAS TRANS SERVICE" MAS\'ULIYATI CHEKLANGAN JAMIYAT', 'OOO "ANAS TRANS SERVICE"'],
            'qo\'shtirnoqsiz, xato yozilgan' => ["MA'SULIYATI CHEKLANGAN JAMIYAT BMW EVRO TRANS", 'OOO "BMW EVRO TRANS"'],
            'rus iborasi ikki marta' => ['Общество с ограниченной или дополнительной ответственностью ОБЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ "GREAT FIVE STAR PLUS"', 'OOO "GREAT FIVE STAR PLUS"'],
            'backtick apostrof' => ['МЧЖ "TAPESH TRANS" MAS`ULIYATI CHEKLANGAN JAMIYAT', 'OOO "TAPESH TRANS"'],
            'ʼ apostrof, xorijiy korxona' => ['Masʼuliyati cheklangan jamiyati "SHARQ INVESTING" MAS\'ULIYATI CHEKLANGAN JAMIYAT XORIJIY KORXONA', 'OOO "SHARQ INVESTING"'],
            'kesilgan JAMIY' => ['Общество с ограниченной или дополнительной ответственностью "RAXIMBERGAN UMARBEK" MAS\'ULIYATI CHEKLANGAN JAMIY', 'OOO "RAXIMBERGAN UMARBEK"'],
            'faqat qisqartma' => ['МЧЖ MULTI MAX', 'OOO "MULTI MAX"'],
            'MSHJ' => ['MSHJ RAVON TEXNO TRANS', 'OOO "RAVON TEXNO TRANS"'],
            'xususiy korxona' => ['X/K "GLOBAL LION INVEST" XUSUSIY KORXONA', 'OOO "GLOBAL LION INVEST"'],
            'XK qo\'shtirnoqsiz' => ['ХК NARGIZ-J', 'OOO "NARGIZ-J"'],
            'частное предприятие' => ['ХК ЧАСТНОЕ ПРЕДПРИЯТИЕ "DILNURA-SURXON"', 'OOO "DILNURA-SURXON"'],
            'oilaviy korxona' => ['OILAVIY KORXONA "MUZAFFAR MUHAMMADALI MIRZO" oilaviy korxonasi', 'OOO "MUZAFFAR MUHAMMADALI MIRZO"'],
            'YaTT' => ['ЯТТ SHOVXUNOV BOLIYAR BAKIYEVICH', 'OOO "SHOVXUNOV BOLIYAR BAKIYEVICH"'],
            'kirill nom katta harfga' => ['"Курама авто сервис" масъулияти чекланган жамият', 'OOO "КУРАМА АВТО СЕРВИС"'],
            'kirill ibora, nom ichida KORXONA' => ['МЧЖ 148-АВТОКОРХОНА МАЪСУЛИЯТИ ЧЕКЛАНГАН ЖАМИЯТИ', 'OOO "148-АВТОКОРХОНА"'],
            'yoki qo\'shimcha mas\'uliyatli' => ['Mas’uliyati cheklangan yoki qo‘shimcha mas’uliyatli jamiyat KARGO-TRANS-UNIVERSAL', 'OOO "KARGO-TRANS-UNIVERSAL"'],
            'yulduzchali O\'O\'B' => ["O'O'B X********* M******* X************", 'OOO "X********* M******* X************"'],
            'yulduzchali YaTT' => ['ЯТТ Л**** З********** М************', 'OOO "Л**** З********** М************"'],
            'qo\'shtirnoq ichida bo\'sh joy' => ['ХУСУСИЙ "CHINA AUTO FEYSAL " MAS\'ULIYATI CHEKLANGAN JAMIYAT', 'OOO "CHINA AUTO FEYSAL"'],
            'kichik harf' => ['МЧЖ Инвест-Мир-сервис', 'OOO "ИНВЕСТ-МИР-СЕРВИС"'],
            'nom ichidagi qisqartma saqlanadi' => ['МЧЖ AJ TRANS LOGISTIC', 'OOO "AJ TRANS LOGISTIC"'],
            'allaqachon formatlangan' => ['OOO "ALFA TRANS"', 'OOO "ALFA TRANS"'],
        ];
    }

    #[DataProvider('names')]
    public function test_format(string $input, string $expected): void
    {
        $this->assertSame($expected, CompanyName::format($input));
    }

    public function test_empty_and_form_only_values(): void
    {
        $this->assertSame('', CompanyName::format(null));
        $this->assertSame('', CompanyName::format('   '));
        // nomning o'zi yo'q — asl qiymat qoladi
        $this->assertSame('МЧЖ', CompanyName::format('МЧЖ'));
    }
}
