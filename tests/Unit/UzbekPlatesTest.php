<?php

namespace Tests\Unit;

use App\Support\UzbekPlates;
use PHPUnit\Framework\TestCase;

class UzbekPlatesTest extends TestCase
{
    public function test_both_car_formats(): void
    {
        $this->assertSame(['01A123BC'], UzbekPlates::extract('01A123BC'));
        $this->assertSame(['01176BBB'], UzbekPlates::extract('01176BBB'));
    }

    public function test_separators_and_case_are_normalized(): void
    {
        $this->assertSame(['50999TBA'], UzbekPlates::extract('50 999 TBA'));
        $this->assertSame(['10S208NB'], UzbekPlates::extract('10 s 208-nb'));
        $this->assertSame(['40176BBB'], UzbekPlates::extract('40-176-BBB'));
    }

    public function test_every_region_including_75_and_90(): void
    {
        foreach (UzbekPlates::REGIONS as $region) {
            $this->assertSame(["{$region}A641LA"], UzbekPlates::extract("{$region}A641LA"), $region);
        }

        $this->assertSame([], UzbekPlates::extract('99A641LA')); // bunday hudud yo'q
        $this->assertSame([], UzbekPlates::extract('02123ABC'));
    }

    public function test_trailers_and_foreign_plates_are_ignored(): void
    {
        $this->assertSame([], UzbekPlates::extract('506695BA'));       // pritsep
        $this->assertSame([], UzbekPlates::extract('50 6695 BA'));     // pritsep
        $this->assertSame([], UzbekPlates::extract('A123BC 77'));      // Rossiya
        $this->assertSame([], UzbekPlates::extract('123ABC02'));       // Qozog'iston
        $this->assertSame([], UzbekPlates::extract('KZ 777 AAA 02'));
    }

    public function test_not_cut_out_of_longer_tokens(): void
    {
        $this->assertSame([], UzbekPlates::extract('X01A123BC'));
        $this->assertSame([], UzbekPlates::extract('01A123BCD'));
        $this->assertSame([], UzbekPlates::extract('101123ABC'));
    }

    public function test_extracts_all_from_free_text_without_duplicates(): void
    {
        $text = 'Tyagach: 50999TBA / pritsep 506695BA; ikkinchi 01 A 222 MC, yana 50999TBA';

        $this->assertSame(['50999TBA', '01A222MC'], UzbekPlates::extract($text));
    }

    public function test_empty_values(): void
    {
        $this->assertSame([], UzbekPlates::extract(null));
        $this->assertSame([], UzbekPlates::extract('   '));
        $this->assertSame([], UzbekPlates::extract('Davlat raqami'));
    }

    public function test_is_plate(): void
    {
        $this->assertTrue(UzbekPlates::isPlate('01 A 123 BC'));
        $this->assertFalse(UzbekPlates::isPlate('01A123BC and more'));
        $this->assertFalse(UzbekPlates::isPlate('506695BA'));
    }

    public function test_cyrillic_lookalike_letters_become_latin(): void
    {
        $this->assertSame(['01A210NA'], UzbekPlates::extract('01А210NA'));     // kirill А
        $this->assertSame(['01261GDA'], UzbekPlates::extract('01261GDА'));     // oxirida kirill А
        $this->assertSame(['01H211RC'], UzbekPlates::extract('01Н211RС'));     // kirill Н va С
        $this->assertSame(['10C208NB'], UzbekPlates::extract('10 с 208 nb'));  // kichik kirill с -> lotin C
        $this->assertSame([], UzbekPlates::extract('01516ЗPKA'));              // З lotinga o'xshamaydi
        $this->assertTrue(UzbekPlates::isPlate('01А210NA'));
    }
}
