<?php

namespace App\Console\Commands;

use App\Support\CompanyName;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 2-bosqich: "Korxona nomi" ustunidagi nomlarni tozalab OOO "NOMI" ko'rinishiga
 * keltiradi (App\Support\CompanyName). Ustun sarlavhasi bo'yicha topiladi,
 * shuning uchun istalgan Excel'ga qo'llash mumkin.
 */
class MintransCleanNamesCommand extends Command
{
    protected $signature = 'mintrans:clean-names
                            {file : Excel fayl (1-bosqich natijasi yoki "Korxona nomi" ustuni bor istalgan fayl)}
                            {--output= : Natijani shu faylga yozish (bo\'sh bo\'lsa asl fayl ustidan)}
                            {--column=Korxona nomi : Nomlar ustunining sarlavhasi}';

    protected $description = 'Korxona nomlaridan yuridik shakl so\'zlarini olib, OOO "NOMI" ko\'rinishiga keltiradi (2-bosqich)';

    public function handle(): int
    {
        $file = $this->argument('file');
        $output = $this->option('output') ?: $file;

        if (! is_file($file)) {
            $this->error("Fayl topilmadi: {$file}");

            return self::FAILURE;
        }

        $this->info('2-bosqich: Korxona nomlarini tozalash va formatlash');

        $book = IOFactory::load($file);
        $sheet = $book->getActiveSheet();
        $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, false, false, true)[1] ?? [];
        $column = array_search($this->option('column'), array_map(fn ($v) => trim((string) $v), $header), true);

        if ($column === false) {
            $this->error('"' . $this->option('column') . '" ustuni topilmadi.');

            return self::FAILURE;
        }

        $changed = 0;
        $total = 0;
        $examples = [];

        for ($row = 2, $last = $sheet->getHighestDataRow(); $row <= $last; $row++) {
            $cell = $sheet->getCell("{$column}{$row}");
            $before = trim((string) $cell->getValue());

            if ($before === '') {
                continue;
            }

            $total++;
            $after = CompanyName::format($before);

            if ($after !== $before) {
                $cell->setValueExplicit($after, DataType::TYPE_STRING);
                $changed++;

                if (count($examples) < 5) {
                    $examples[] = "  {$before}\n    → {$after}";
                }
            }
        }

        (new Xlsx($book))->save($output);

        foreach ($examples as $example) {
            $this->line($example);
        }

        $this->info("Korxona nomlari: {$total} ta, shundan formatlandi: {$changed} ta");

        return self::SUCCESS;
    }
}
