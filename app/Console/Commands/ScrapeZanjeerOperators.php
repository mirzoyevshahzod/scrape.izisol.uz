<?php

namespace App\Console\Commands;

use App\Services\EgsCrmAuthException;
use App\Services\EgsCrmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Pipeline (qozoq:warehouse-pipeline) merge faylidagi har bir kompaniya
 * (E ustuni) bo'yicha EGS CRM kontragentlaridan (API: GET /contragents)
 * mas'ul xodimni topib, H ustuniga ("Meneger Ismi sharifi") yozadi.
 *
 * Bir nechta kontragent chiqsa — nomi (yuridik shakl va qo'shtirnoqlarsiz)
 * aynan mos kelgani, bo'lmasa birinchisi. Mas'ul xodim yo'q yoki
 * "EGS OPERATION" bo'lsa "-".
 *
 * Natija storage/app/public/operators_<vaqt>.xlsx ga saqlanadi va yo'li
 * storage/app/last_operator_file.txt ga yoziladi (pipeline va API shundan oladi).
 */
class ScrapeZanjeerOperators extends Command
{
    protected $signature = 'scrape:zanjeer-operators {file : storage/app ga nisbatan yo\'l, masalan merge/merged_....xlsx}';

    protected $description = 'Kompaniya nomi bo\'yicha EGS CRM kontragentlaridan mas\'ul xodimni topib, Excel\'ning H ustuniga yozadi';

    private const COMPANY_COLUMN = 'E';

    private const OPERATOR_COLUMN = 'H';

    private const OPERATOR_HEADER = 'Meneger Ismi sharifi';

    private const EMPTY = '-';

    public function handle(EgsCrmClient $crm): int
    {
        $inputFile = storage_path('app/' . $this->argument('file'));

        if (! file_exists($inputFile)) {
            Log::error("INPUT FILE NOT FOUND: {$inputFile}");
            $this->error("Fayl topilmadi: {$inputFile}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($inputFile);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue(self::OPERATOR_COLUMN . '1', self::OPERATOR_HEADER);

        $cache = [];

        for ($row = 2, $last = $sheet->getHighestRow(); $row <= $last; $row++) {
            $company = trim((string) $sheet->getCell(self::COMPANY_COLUMN . $row)->getValue());

            if ($company === '') {
                continue;
            }

            $search = self::searchName($company);

            if (! array_key_exists($search, $cache)) {
                try {
                    $cache[$search] = self::pickOperator($crm->contragents($search), $company);
                } catch (EgsCrmAuthException $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                } catch (Throwable $e) {
                    Log::error('CRM contragent search failed', ['company' => $company, 'error' => $e->getMessage()]);
                    $this->error("{$search}: " . $e->getMessage());
                    $cache[$search] = 'ERROR';
                }

                $this->line("{$search} => {$cache[$search]}");
            }

            $sheet->setCellValue(self::OPERATOR_COLUMN . $row, $cache[$search]);
        }

        $outputFile = Storage::disk('public')->path('operators_' . now()->format('Y-m-d_H-i-s') . '.xlsx');

        if (! is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0775, true);
        }

        (new Xlsx($spreadsheet))->save($outputFile);
        file_put_contents(storage_path('app/last_operator_file.txt'), $outputFile);

        $this->info("Tayyor: {$outputFile} (" . count($cache) . ' ta noyob kompaniya)');

        return self::SUCCESS;
    }

    /** OOO "NOMI" -> NOMI (CRM qidiruviga) */
    public static function searchName(string $company): string
    {
        $name = str_replace(['"', '«', '»', '“', '”'], '', preg_replace('/(?<![\p{L}\p{N}])(OOO|ООО)(?![\p{L}\p{N}])/ui', '', $company));

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * @param  array<int,array<string,mixed>>  $contragents
     */
    public static function pickOperator(array $contragents, string $company): string
    {
        if (! $contragents) {
            return self::EMPTY;
        }

        $wanted = FillPlateOperatorsCommand::companySortKey($company);
        $match = collect($contragents)->first(fn (array $c) => collect([$c['company_name'] ?? '', $c['name'] ?? '', $c['legal_name'] ?? ''])
            ->contains(fn ($name) => $name !== '' && FillPlateOperatorsCommand::companySortKey((string) $name) === $wanted)) ?? $contragents[0];

        $operator = trim((string) ($match['employee']['name'] ?? ''));

        return $operator === '' || mb_strtoupper($operator) === 'EGS OPERATION' ? self::EMPTY : $operator;
    }
}
