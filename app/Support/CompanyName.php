<?php

namespace App\Support;

/**
 * Mintrans'dagi korxona nomini yagona ko'rinishga keltiradi: OOO "NOMI".
 *
 *   МЧЖ "ANAS TRANS SERVICE" MAS'ULIYATI CHEKLANGAN JAMIYAT  -> OOO "ANAS TRANS SERVICE"
 *   MA'SULIYATI CHEKLANGAN JAMIYAT BMW EVRO TRANS            -> OOO "BMW EVRO TRANS"
 *   ЯТТ SHOVXUNOV BOLIYAR BAKIYEVICH                         -> OOO "SHOVXUNOV BOLIYAR BAKIYEVICH"
 *   O'O'B X********* M******* X************                  -> OOO "X********* M******* X************"
 *
 * Har qanday yuridik shakl (MChJ, XK, YaTT, oilaviy korxona, O'O'B ...) olib
 * tashlanadi va nom KATTA harflarda OOO "..." ichiga olinadi. Mintrans
 * yulduzcha bilan yashirgan nomlar o'zgarmaydi (faqat shakl so'zi olinadi).
 */
class CompanyName
{
    /** Nomning istalgan joyida uchrasa olib tashlanadigan shakl iboralari (apostrof ' ga keltirilgan holda) */
    private const PHRASES = [
        // rus
        'общество\s+с\s+ограниченной\s+(?:или\s+дополнительной\s+)?ответственностью',
        'общество\s+с\s+дополнительной\s+ответственностью',
        '(?:открытое\s+|закрытое\s+)?акционерное\s+общество',
        '(?:государственное\s+)?унитарное\s+предприятие',
        '(?:частное|семейное|совместное|иностранное|дочернее)\s+предприятие',
        '(?:крестьянско[-\s]?)?фермерское\s+хозяйство',
        'индивидуальный\s+предприниматель',
        // o'zbek (lotin); uzunroq variant oldin
        "ma'?s'?uliyat\w*\s+cheklangan\s+yoki\s+qo'shimcha\s+ma'?s'?uliyatli\s+jamiyat\w*",
        "ma'?s'?uliyat\w*\s+cheklangan(?:\s+jamiy\w*)?",
        "(?:xususiy|oilaviy|xorijiy|qo'shma|unitar|davlat\s+unitar)\s+korxona\w*",
        'xususiy\s+mulk\w*',
        "(?:fermer|dehqon)\s+xo'jalig\w*",
        'aksiyadorlik\s+jamiyat\w*',
        'yakka\s+tartibdagi\s+tadbirkor\w*',
        "o'zini\s+o'zi\s+band\s+qilgan(?:\s+shaxs\w*)?",
        // o'zbek (kirill)
        "ма[ъ']?с[ъ']?улият\w*\s+чекланган\s+ёки\s+қўшимча\s+ма[ъ']?с[ъ']?улиятли\s+жамият\w*",
        "ма[ъ']?с[ъ']?улият\w*\s+чекланган(?:\s+жамият\w*)?",
        '(?:хусусий|оилавий|хорижий|қўшма|унитар|давлат\s+унитар)\s+корхона\w*',
        'хусусий\s+мулк\w*',
        '(?:фермер|деҳқон|дехқон|дехкон)\s+х[ўу]жалиг\w*',
        'акциядорлик\s+жамият\w*',
        'якка\s+тартибдаги\s+тадбиркор\w*',
    ];

    /**
     * Faqat nomning boshida yoki oxirida turganda olinadigan qisqartmalar —
     * nom ichidagi tasodifiy mos kelishlarni (masalan "AJ TRANS") buzmaslik uchun.
     */
    private const ABBREVIATIONS = [
        'МЧЖ', 'MCHJ', 'MSHJ', 'ООО', 'OOO', 'LLC', 'ТОО',
        'ХК', 'XK', 'Х/К', 'X/K', 'ШК', 'ЧП', 'ИП', 'ХФ', 'XF',
        'ЯТТ', 'YATT', 'ОАЖ', "O'O'B", 'ЎЎБ',
        'ХУСУСИЙ', 'XUSUSIY', 'ЧАСТНОЕ',
        // AJ, QK, FX, DX kabi 2 harfli qisqartmalar ataylab yo'q: nom boshidagi
        // so'z bilan adashadi ("МЧЖ AJ TRANS"), Mintrans ma'lumotida esa uchramadi
    ];

    public static function format(?string $name): string
    {
        $original = self::squash((string) $name);

        if ($original === '') {
            return '';
        }

        // turli apostroflarni bitta ' ga keltiramiz (MAS`ULIYATI, MASʼULIYATI, MAS‘ULIYATI ...)
        $text = preg_replace("/[`ʼʻ’‘´]/u", "'", $original);

        // qo'shtirnoq ichidagi nom bo'lsa — aynan u (atrofidagi shakl so'zlari o'z-o'zidan tushib qoladi)
        if (preg_match('/["«“„]\s*([^"«»“”„]*?[\p{L}\p{N}*][^"«»“”„]*?)\s*["»”“]/u', $text, $m)) {
            $text = $m[1];
        }

        $name = self::stripForms($text);

        if ($name === '') {
            return $original;
        }

        return 'OOO "' . mb_strtoupper($name) . '"';
    }

    private static function stripForms(string $text): string
    {
        $boundaryL = '(?<![\p{L}\p{N}])';
        $boundaryR = '(?![\p{L}\p{N}])';

        foreach (self::PHRASES as $phrase) {
            $text = preg_replace('/' . $boundaryL . $phrase . $boundaryR . '/iu', ' ', $text);
        }

        $abbr = implode('|', array_map(fn ($a) => preg_quote($a, '/'), self::ABBREVIATIONS));

        // boshidagi va oxiridagi qisqartmalar (bir nechta bo'lishi mumkin: "МЧЖ ООО ...")
        do {
            $before = $text;
            $text = self::squash(preg_replace('/["«»“”„]/u', ' ', $text));
            $text = preg_replace('/^(?:' . $abbr . ')' . $boundaryR . '[\s,.\-:]*/iu', '', $text);
            $text = preg_replace('/[\s,.\-:]*' . $boundaryL . '(?:' . $abbr . ')$/iu', '', $text);
        } while ($text !== $before);

        return trim(self::squash($text), " ,.;:-'");
    }

    private static function squash(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
