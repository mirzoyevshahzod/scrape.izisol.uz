<?php

namespace App\Console\Commands;

use App\Models\JobRun;
use App\Models\OrginfoInn;
use App\Services\OrginfoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 3-bosqich: "Korxona nomi" bo'yicha orginfo.uz'dan INN topib, faylni
 * auto_import_template formatiga keltiradi:
 *   A–M  — 2-bosqichdagi ustunlar (o'zgarishsiz),
 *   N    — "инн" (topilmasa bo'sh),
 *   O    — "Yangi telefon raqami" (bo'sh ustun).
 *
 * Har bir noyob nom bir marta qidiriladi; natijalar orginfo_inns jadvalida
 * saqlanadi va keyingi ishlarda qayta so'ralmaydi (OrginfoClient).
 */
class MintransInnCommand extends Command
{
    protected $signature = 'mintrans:inn
                            {file : 2-bosqich natijasi ("Korxona nomi" ustuni bor Excel)}
                            {--output= : Natijani shu faylga yozish (bo\'sh bo\'lsa asl fayl ustidan)}
                            {--delay=1000 : orginfo.uz\'ga parallel so\'rovlar guruhlari orasidagi pauza, ms}
                            {--run= : Boshqaruv ishi (job_runs.id) — jarayon shu yozuvga yoziladi}';

    protected $description = 'Korxona nomlari bo\'yicha orginfo.uz\'dan INN topib, auto_import_template formatiga keltiradi (3-bosqich)';

    public const INN_HEADER = 'инн';

    public const PHONE_HEADER = 'Yangi telefon raqami';

    /** orginfo.uz'ga bir vaqtda yuboriladigan so'rovlar soni (4 tasida bloklamadi, ~4 s/nom) */
    private const CONCURRENCY = 4;

    /** auto_import_template.xlsx'dagi ustun enlari */
    private const WIDTHS = ['A' => 24, 'E' => 73.55, 'F' => 36.11, 'L' => 13.78, 'M' => 24.66, 'N' => 22, 'O' => 22];

    public function handle(OrginfoClient $client): int
    {
        $file = $this->argument('file');
        $output = $this->option('output') ?: $file;
        $delayMs = max(500, (int) $this->option('delay'));
        $run = $this->option('run') ? JobRun::find($this->option('run')) : null;

        if (! is_file($file)) {
            $this->error("Fayl topilmadi: {$file}");

            return self::FAILURE;
        }

        $this->info("3-bosqich: orginfo.uz'dan INN qidirish");

        $book = IOFactory::load($file);
        $sheet = $book->getActiveSheet();
        $header = array_map(fn ($v) => trim((string) $v), $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, false, false, true)[1] ?? []);
        $nameCol = array_search('Korxona nomi', $header, true);

        if ($nameCol === false) {
            $this->error('"Korxona nomi" ustuni topilmadi.');

            return self::FAILURE;
        }

        // N/O: fayl avval ham 3-bosqichdan o'tgan bo'lsa — o'sha ustunlar, aks holda shablondagi joyi
        $innCol = $this->findColumn($header, self::INN_HEADER) ?? 'N';
        $phoneCol = $this->findColumn($header, self::PHONE_HEADER) ?? 'O';
        $sheet->setCellValue("{$innCol}1", self::INN_HEADER);
        $sheet->setCellValue("{$phoneCol}1", self::PHONE_HEADER);

        // nom => qatorlar
        $rowsByName = [];
        for ($row = 2, $last = $sheet->getHighestDataRow(); $row <= $last; $row++) {
            $name = trim((string) $sheet->getCell("{$nameCol}{$row}")->getValue());

            if ($name !== '') {
                $rowsByName[$name][] = $row;
            }
        }

        $names = array_keys($rowsByName);
        $stats = ['total' => count($names), 'done' => 0, 'found' => 0, 'not_found' => 0, 'errors' => 0, 'cached' => 0];
        $this->line("Noyob korxona nomlari: {$stats['total']} ta (" . array_sum(array_map('count', $rowsByName)) . ' qator)');
        $this->progress($run, $stats);

        try {
            // orginfo qidiruvi serverda ~11-15 s davom etadi — nomlar CONCURRENCY tadan parallel so'raladi
            foreach (array_chunk($names, self::CONCURRENCY) as $batch) {
                $pending = [];
                $results = [];

                foreach ($batch as $name) {
                    $cached = OrginfoInn::cachedFor($name);

                    if ($cached) {
                        $results[$name] = ['inn' => $cached->inn, 'cached' => true];
                    } else {
                        $pending[] = $name;
                    }
                }

                if ($pending) {
                    foreach ($client->findInnMany($pending) as $name => $result) {
                        if (! array_key_exists('error', $result)) {
                            OrginfoInn::updateOrCreate(['name' => $name], ['inn' => $result['inn'], 'checked_at' => now()]);
                        }

                        $results[$name] = $result + ['cached' => false];
                    }
                }

                foreach ($batch as $name) {
                    $result = $results[$name];
                    $label = '[' . ($stats['done'] + 1) . '/' . count($names) . '] ' . OrginfoClient::innerName($name) . ' — ';
                    $fromCache = $result['cached'] ? ' (bazadan)' : '';

                    if (array_key_exists('error', $result)) {
                        Log::error('Orginfo INN error', ['name' => $name, 'error' => $result['error']]);
                        $stats['errors']++;
                        $this->line("⚠️ {$label}xatolik: " . mb_substr(preg_replace('/\s+/', ' ', $result['error']), 0, 150));
                    } else {
                        foreach ($rowsByName[$name] as $row) {
                            $result['inn']
                                ? $sheet->setCellValueExplicit("{$innCol}{$row}", $result['inn'], DataType::TYPE_STRING)
                                : $sheet->setCellValue("{$innCol}{$row}", null);
                        }

                        $stats['cached'] += $result['cached'] ? 1 : 0;

                        if ($result['inn']) {
                            $stats['found']++;
                            $this->line("✅ {$label}INN {$result['inn']}{$fromCache}");
                        } else {
                            $stats['not_found']++;
                            $this->line("❌ {$label}INN topilmadi{$fromCache}");
                        }
                    }

                    $stats['done']++;

                    if ($stats['done'] % 50 === 0) {
                        $this->save($book, $output);
                    }
                }

                $this->progress($run, $stats);

                if ($pending && $stats['done'] < count($names)) {
                    Sleep::usleep($delayMs * 1000);
                }
            }
        } finally {
            $this->applyTemplate($book);
            $this->save($book, $output);
        }

        $this->line('');
        $this->info("Nomlar: {$stats['total']} · INN topildi: {$stats['found']} · Topilmadi: {$stats['not_found']} · Xatolik: {$stats['errors']}"
            . ($stats['cached'] ? " · bazadan olindi: {$stats['cached']}" : ''));

        return $stats['total'] > 0 && $stats['errors'] === $stats['total'] ? self::FAILURE : self::SUCCESS;
    }

    private function findColumn(array $header, string $title): ?string
    {
        foreach ($header as $col => $value) {
            if (mb_strtolower($value) === mb_strtolower($title)) {
                return $col;
            }
        }

        return null;
    }

    /** auto_import_template ko'rinishi: oddiy (qalin bo'lmagan) sarlavha, shablondagi ustun enlari */
    private function applyTemplate(Spreadsheet $book): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->getStyle('A1:O1')->getFont()->setBold(false);

        foreach (self::WIDTHS as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function save(Spreadsheet $book, string $output): void
    {
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }

        (new Xlsx($book))->save($output);
    }

    /** Sahifa uchun jarayon (job_runs.params.inn) */
    private function progress(?JobRun $run, array $stats): void
    {
        if (! $run) {
            return;
        }

        $params = $run->fresh()?->params ?? [];
        $params['inn'] = $stats;
        JobRun::whereKey($run->id)->update(['params' => json_encode($params, JSON_UNESCAPED_UNICODE)]);
    }
}
