<?php

namespace App\Console\Commands;

use App\Models\JobRun;
use App\Models\MintransNotFound;
use App\Services\MintransClient;
use App\Support\UzbekPlates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * 1-bosqich: Excel'ning A ustunidan O'zbekiston avtomobil raqamlarini ajratib
 * (qolgan yozuvlar tashlab yuboriladi), har birini Mintrans API'dan so'raydi.
 *
 * - topildi    -> har bir litsenziya natija Excel'iga alohida qator;
 * - topilmadi  -> mintrans_not_found jadvaliga (Excel'ga yozilmaydi);
 * - xatolik    -> mintrans_not_found jadvaliga xato matni bilan + log.
 *
 * Natija fayli har 50 ta raqamdan keyin saqlab boriladi (jarayon uzilsa
 * ma'lumot yo'qolmaydi). 2-bosqich — mintrans:clean-names.
 */
class MintransScrapeCommand extends Command
{
    protected $signature = 'mintrans:scrape
                            {file : A ustunida avtoraqamlar bo\'lgan Excel (xlsx/xls/csv)}
                            {--output= : Natija fayli (bo\'sh bo\'lsa storage/app/mintrans/results/mintrans_result_<vaqt>.xlsx)}
                            {--delay=300 : So\'rovlar orasidagi pauza, ms (davlat saytini yuklamaslik uchun kamida 300)}
                            {--skip-known-missing : Avval "topilmadi" deb yozilgan raqamlarni qayta so\'ramaslik}
                            {--run= : Boshqaruv ishi (job_runs.id) — jarayon shu yozuvga yozib boriladi}
                            {--plates-output= : Ajratilgan avtoraqamlar ro\'yxatini (so\'rovlardan oldin) shu Excel faylga yozish}';

    protected $description = 'Excel\'dagi O\'zbekiston avtoraqamlari bo\'yicha Mintrans litsenziya ma\'lumotlarini oladi (1-bosqich)';

    /** Natija Excel ustunlari — mintrans_result namunasidagi tartib va kenglik */
    public const COLUMNS = [
        'A' => ['Rusumi', 'rusumi', 41.1],
        'B' => ['Yuk ko\'tarish qobiliyati', 'yuk', 29.4],
        'C' => ['Litsenziya varaqasi', 'litsenziya', 23.4],
        'D' => ['Davlat raqami', 'raqam', 16.4],
        'E' => ['Korxona nomi', 'korxona', 60],
        'F' => ['Telefon raqami', 'telefon', 17.6],
        'G' => ['Faoliyat turi', 'faoliyat', 60],
        'H' => ['Transport turi', 'transport', 17.6],
        'I' => ['Yuk turi', 'yuk_turi', 28.1],
        'J' => ['Berilgan sana', 'berilgan', 16.4],
        'K' => ['Amal qilish muddati', 'muddati', 23.4],
        'L' => ['Holati', 'holati', 18.7],
        'M' => ['Hududiy boshqarma', 'boshqarma', 51.8],
    ];

    /** Matn sifatida yoziladigan ustunlar (Excel raqamga aylantirmasin) */
    private const TEXT_COLUMNS = ['D', 'F'];

    private const SAVE_EVERY = 50;

    public function handle(MintransClient $client): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Fayl topilmadi: {$file}");

            return self::FAILURE;
        }

        $output = $this->option('output') ?: storage_path('app/mintrans/results/mintrans_result_' . now()->format('Y_m_d_H_i_s') . '.xlsx');
        $delayMs = max(300, (int) $this->option('delay'));
        $run = $this->option('run') ? JobRun::find($this->option('run')) : null;

        $this->info("1-bosqich: Mintrans'dan ma'lumot olish");

        // ~300 ming qatorli faylni o'qish ~300 MB oladi
        $this->ensureMemoryLimit(1024);

        [$plates, $dropped, $rows] = $this->readPlates($file);
        $total = count($plates);

        if ($this->option('plates-output')) {
            $this->writePlates($plates, $this->option('plates-output'));
        }

        $skipped = 0;
        if ($this->option('skip-known-missing') && $plates) {
            $known = MintransNotFound::whereIn('plate', $plates)->where('status', MintransNotFound::NOT_FOUND)->pluck('plate')->all();
            $plates = array_values(array_diff($plates, $known));
            $skipped = $total - count($plates);
        }

        $this->line("Fayldagi to'ldirilgan qatorlar: {$rows} ta");
        $this->line("Fayldagi O'zbekiston avtoraqamlari: {$total} ta; raqam bo'lmagan yozuvlar tashlab yuborildi: {$dropped} ta"
            . ($skipped ? "; avval topilmagani uchun o'tkazib yuborildi: {$skipped} ta" : ''));

        $book = $this->newBook();
        $sheet = $book->getActiveSheet();
        $row = 2;
        $stats = ['total' => count($plates), 'done' => 0, 'found' => 0, 'not_found' => 0, 'errors' => 0, 'dropped' => $dropped, 'skipped' => $skipped];
        $this->progress($run, $stats, $output);

        try {
            foreach ($plates as $i => $plate) {
                $prefix = '[' . ($i + 1) . '/' . count($plates) . "] {$plate} — ";
                $mark = fn (string $icon) => "{$icon} {$prefix}";

                try {
                    $licenses = $client->search($plate);

                    if ($licenses) {
                        foreach ($licenses as $license) {
                            $this->writeRow($sheet, $row++, $license);
                        }

                        MintransNotFound::where('plate', $plate)->delete();
                        $stats['found']++;
                        $this->line($mark('✅') . "ma'lumot chiqdi" . (count($licenses) > 1 ? ' (' . count($licenses) . ' ta litsenziya)' : ''));
                    } else {
                        MintransNotFound::record($plate, MintransNotFound::NOT_FOUND, null, $run?->id);
                        $stats['not_found']++;
                        $this->line($mark('❌') . "ma'lumot chiqmadi");
                    }
                } catch (Throwable $e) {
                    $message = $this->shortError($e);
                    Log::error('Mintrans API error', ['plate' => $plate, 'error' => $e->getMessage()]);
                    MintransNotFound::record($plate, MintransNotFound::ERROR, $message, $run?->id);
                    $stats['errors']++;
                    $this->line($mark('⚠️') . "xatolik: {$message}");
                }

                $stats['done']++;
                $this->progress($run, $stats, $output);

                if ($stats['done'] % self::SAVE_EVERY === 0) {
                    $this->save($book, $output);
                }

                if ($i < count($plates) - 1) {
                    Sleep::usleep($delayMs * 1000);
                }
            }
        } finally {
            $this->save($book, $output);
        }

        $this->line('');
        $this->info("Jami: {$stats['total']} · Topildi: {$stats['found']} · Topilmadi: {$stats['not_found']} · Xatolik: {$stats['errors']}");
        $this->line("Natija: {$output}");

        // hamma so'rov xato bo'lsa (masalan API yopiq) — ish muvaffaqiyatsiz
        return $stats['total'] > 0 && $stats['errors'] === $stats['total'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * A ustunidagi barcha raqamlar (kiritilish tartibida, takrorlarsiz),
     * raqam topilmagan va jami to'ldirilgan kataklar soni.
     *
     * @return array{0:array<int,string>,1:int,2:int}
     */
    private function readPlates(string $file): array
    {
        $sheet = IOFactory::load($file)->getActiveSheet();
        $plates = [];
        $dropped = 0;
        $rows = 0;

        foreach ($sheet->getRowIterator() as $excelRow) {
            $value = trim((string) $sheet->getCell('A' . $excelRow->getRowIndex())->getFormattedValue());

            if ($value === '') {
                continue;
            }

            $rows++;

            $found = UzbekPlates::extract($value);

            if (! $found) {
                $dropped++;

                continue;
            }

            foreach ($found as $plate) {
                $plates[$plate] = true;
            }
        }

        return [array_keys($plates), $dropped, $rows];
    }

    /** Ajratilgan raqamlar ro'yxati — uzoq jarayon boshlanishidan oldin tekshirib olish uchun */
    private function writePlates(array $plates, string $path): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A1', 'Davlat raqami');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(16);

        foreach ($plates as $i => $plate) {
            $sheet->setCellValueExplicit('A' . ($i + 2), $plate, DataType::TYPE_STRING);
        }

        $this->save($book, $path);
        $this->line('Raqamlar ro\'yxati: ' . $path);
    }

    /** CLI'da memory_limit kichik bo'lsa (masalan 128M) — kamida $mb MB gacha ko'taradi */
    private function ensureMemoryLimit(int $mb): void
    {
        $current = ini_get('memory_limit');

        if ($current === '-1') {
            return;
        }

        $bytes = (int) $current * match (strtoupper(substr($current, -1))) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        if ($bytes < $mb * 1024 ** 2) {
            ini_set('memory_limit', "{$mb}M");
        }
    }

    private function newBook(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();

        foreach (self::COLUMNS as $col => [$title, , $width]) {
            $sheet->setCellValue("{$col}1", $title);
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getStyle('A1:M1')->getFont()->setBold(true);

        return $book;
    }

    private function writeRow(Worksheet $sheet, int $row, array $license): void
    {
        foreach (self::COLUMNS as $col => [, $key]) {
            $value = $license[$key] ?? '';

            if ($value === '') {
                continue;
            }

            if (in_array($col, self::TEXT_COLUMNS, true)) {
                $sheet->setCellValueExplicit("{$col}{$row}", $value, DataType::TYPE_STRING);
            } elseif ($col === 'B' && is_numeric($value)) {
                $sheet->setCellValue("{$col}{$row}", (float) $value);
            } else {
                $sheet->setCellValueExplicit("{$col}{$row}", $value, DataType::TYPE_STRING);
            }
        }
    }

    private function save(Spreadsheet $book, string $output): void
    {
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }

        (new Xlsx($book))->save($output);
    }

    /** Boshqaruv sahifasi uchun jarayon (job_runs.params.progress) */
    private function progress(?JobRun $run, array $stats, string $output): void
    {
        if (! $run) {
            return;
        }

        $params = $run->fresh()?->params ?? [];
        $params['progress'] = $stats;
        $params['result'] = $output;
        JobRun::whereKey($run->id)->update(['params' => json_encode($params, JSON_UNESCAPED_UNICODE)]);
    }

    private function shortError(Throwable $e): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 150);
    }
}
