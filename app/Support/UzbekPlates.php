<?php

namespace App\Support;

/**
 * Matndan O'zbekiston AVTOMOBIL davlat raqamlarini ajratadi.
 *
 *   jismoniy shaxs:  01 A 123 BC  -> 01A123BC
 *   yuridik shaxs:   01 123 ABC   -> 01123ABC
 *
 * Guruhlar orasida bo'sh joy yoki tire bo'lishi mumkin. Pritsep raqamlari
 * (50 6695 BA) va boshqa davlatlar raqamlari olinmaydi.
 */
class UzbekPlates
{
    /** Hudud kodlari: Toshkent sh., Toshkent v., Sirdaryo, Jizzax, Samarqand, Farg'ona, Namangan, Andijon, Qashqadaryo, Surxondaryo, Buxoro, Navoiy, Xorazm, Qoraqalpog'iston */
    public const REGIONS = ['01', '10', '20', '25', '30', '40', '50', '60', '70', '75', '80', '85', '90', '95'];

    private static ?string $pattern = null;

    /**
     * Matndagi barcha raqamlar, normallashtirilgan (katta harf, bo'sh joysiz), takrorlarsiz.
     *
     * @return array<int,string>
     */
    public static function extract(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        preg_match_all(self::pattern(), mb_strtoupper($text), $matches);

        return array_values(array_unique(array_map(
            fn ($plate) => preg_replace('/[\s-]+/', '', $plate),
            $matches[0]
        )));
    }

    public static function isPlate(string $value): bool
    {
        return self::extract($value) === [preg_replace('/[\s-]+/', '', mb_strtoupper(trim($value)))];
    }

    private static function pattern(): string
    {
        if (self::$pattern === null) {
            $region = '(?:' . implode('|', self::REGIONS) . ')';
            $sep = '[\s-]?';

            // oldida/ortida harf yoki raqam bo'lmasin (uzunroq satr ichidan bo'lak olinmasin)
            self::$pattern = '/(?<![A-Z0-9])(?:'
                . $region . $sep . '[A-Z]' . $sep . '\d{3}' . $sep . '[A-Z]{2}'
                . '|'
                . $region . $sep . '\d{3}' . $sep . '[A-Z]{3}'
                . ')(?![A-Z0-9])/u';
        }

        return self::$pattern;
    }
}
