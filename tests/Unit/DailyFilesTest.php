<?php

namespace Tests\Unit;

use App\Support\DailyFiles;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DailyFilesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/daily-files-' . uniqid();
        mkdir($this->dir);

        // [nomi, o'zgartirilgan vaqti]
        foreach ([
            ['zitic_2026-10-19_06-07-57.xlsx', 1000],
            ['zitic_2026-10-20_06-01-00.xlsx', 2000],
            ['zitic_2026-10-20_11-30-00.xlsx', 3000], // 20-oktabrning qayta ishga tushirilgani
            ['zitic_2026-10-21_06-02-00.xlsx', 4000],
            ['turkey_scrape-2026-10-20-06-08-00.xlsx', 5000],
        ] as [$name, $mtime]) {
            touch("{$this->dir}/{$name}", $mtime);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);

        parent::tearDown();
    }

    public function test_without_date_returns_latest_file(): void
    {
        $this->assertSame('zitic_2026-10-21_06-02-00.xlsx', basename(DailyFiles::latest($this->dir, 'zitic_*.xlsx')));
    }

    public function test_with_date_returns_latest_file_of_that_day(): void
    {
        $this->assertSame('zitic_2026-10-20_11-30-00.xlsx', basename(DailyFiles::latest($this->dir, 'zitic_*.xlsx', '2026-10-20')));
        $this->assertSame('zitic_2026-10-19_06-07-57.xlsx', basename(DailyFiles::latest($this->dir, 'zitic_*.xlsx', '2026-10-19')));
    }

    public function test_with_date_and_no_file_returns_null(): void
    {
        $this->assertNull(DailyFiles::latest($this->dir, 'zitic_*.xlsx', '2026-10-18'));
        $this->assertNull(DailyFiles::latest($this->dir, 'nothing-*.xlsx'));
    }

    public function test_parse_date_option(): void
    {
        $this->assertNull(DailyFiles::parseDateOption(null));
        $this->assertNull(DailyFiles::parseDateOption(''));
        $this->assertSame('2026-01-31', DailyFiles::parseDateOption('2026-01-31'));

        foreach (['2026-02-30', '31-01-2026', '2026-1-5', 'yesterday', '2999-01-01'] as $bad) {
            try {
                DailyFiles::parseDateOption($bad);
                $this->fail("{$bad} qabul qilinmasligi kerak edi");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
