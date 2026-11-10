<?php

namespace App\Console\Commands;

use App\Services\EgsCrmAuthException;
use App\Services\EgsCrmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Pipeline (qozoq:warehouse-pipeline) merge faylidagi har bir kompaniya
 * (E ustuni) bo'yicha EGS CRM'dan menejer va INN topadi:
 *
 *   - menejer (H, "Meneger Ismi sharifi") — kompaniya tashuvchi yoki mijoz
 *     bo'lgan buyurtmalarda eng ko'p qatnashgan OPERATION menejeri (har bir
 *     asosiy buyurtma bir marta); buyurtma bo'lmasa — kontragentning mas'ul
 *     xodimi. Topilmasa yoki "EGS OPERATION" bo'lsa "-";
 *   - INN (D) — o'sha buyurtmalardagi (yoki kontragentdagi) INN, faqat D bo'sh
 *     bo'lsa. Topilmaganlari keyin excel:fill-inn (orginfo) bilan to'ldiriladi.
 *
 * Har bir noyob kompaniya bir marta so'raladi. Natija
 * storage/app/public/operators_<vaqt>.xlsx ga saqlanadi va yo'li
 * storage/app/last_operator_file.txt ga yoziladi (pipeline va API shundan oladi).
 */
class ScrapeZanjeerOperators extends Command
{
    protected $signature = 'scrape:zanjeer-operators {file : storage/app ga nisbatan yo\'l, masalan merge/merged_....xlsx}';

    protected $description = 'Kompaniya nomi bo\'yicha EGS CRM buyurtmalaridan menejer va INN topib, Excel\'ning H va D ustunlariga yozadi';

    private const INN_COLUMN = 'D';

    private const COMPANY_COLUMN = 'E';

    private const OPERATOR_COLUMN = 'H';

    private const OPERATOR_HEADER = 'Meneger Ismi sharifi';

    private const EMPTY = '-';

    /** umumiy nomlar ("TRANS") minglab buyurtma qaytarmasin — eng ko'pi 5 sahifa (500 ta) */
    private const MAX_ORDER_PAGES = 5;

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
        $stats = ['operator' => 0, 'inn' => 0];

        for ($row = 2, $last = $sheet->getHighestRow(); $row <= $last; $row++) {
            $company = trim((string) $sheet->getCell(self::COMPANY_COLUMN . $row)->getValue());

            if ($company === '') {
                continue;
            }

            $search = self::searchName($company);

            if (! array_key_exists($search, $cache)) {
                try {
                    $cache[$search] = $this->lookup($crm, $search, $company);
                } catch (EgsCrmAuthException $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                } catch (Throwable $e) {
                    Log::error('CRM company lookup failed', ['company' => $company, 'error' => $e->getMessage()]);
                    $this->error("{$search}: " . $e->getMessage());
                    $cache[$search] = ['operator' => 'ERROR', 'tin' => null];
                }

                $stats['operator'] += in_array($cache[$search]['operator'], [self::EMPTY, 'ERROR'], true) ? 0 : 1;
                $stats['inn'] += $cache[$search]['tin'] ? 1 : 0;
                $this->line("{$search} => {$cache[$search]['operator']}" . ($cache[$search]['tin'] ? " · INN {$cache[$search]['tin']}" : ''));
            }

            $sheet->setCellValue(self::OPERATOR_COLUMN . $row, $cache[$search]['operator']);

            if ($cache[$search]['tin'] && trim((string) $sheet->getCell(self::INN_COLUMN . $row)->getValue()) === '') {
                $sheet->setCellValueExplicit(self::INN_COLUMN . $row, $cache[$search]['tin'], DataType::TYPE_STRING);
            }
        }

        $outputFile = Storage::disk('public')->path('operators_' . now()->format('Y-m-d_H-i-s') . '.xlsx');

        if (! is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0775, true);
        }

        (new Xlsx($spreadsheet))->save($outputFile);
        file_put_contents(storage_path('app/last_operator_file.txt'), $outputFile);

        $this->info('Kompaniyalar: ' . count($cache) . " ta · menejer topildi: {$stats['operator']} · INN (CRM'dan): {$stats['inn']}");
        $this->info("Tayyor: {$outputFile}");

        return self::SUCCESS;
    }

    /** @return array{operator:string,tin:?string} */
    private function lookup(EgsCrmClient $crm, string $search, string $company): array
    {
        $found = self::fromOrders($crm->orders($search, self::MAX_ORDER_PAGES), $company);

        if ($found['operator'] !== self::EMPTY) {
            return $found;
        }

        // buyurtmasi yo'q (yoki operation'siz) — kontragentning mas'ul xodimi va INN'i
        $contragents = $crm->contragents($search);

        return [
            'operator' => self::pickOperator($contragents, $company),
            'tin' => $found['tin'] ?? self::pickTin($contragents, $company),
        ];
    }

    /** OOO "NOMI" -> NOMI (CRM qidiruviga) */
    public static function searchName(string $company): string
    {
        $name = str_replace(['"', '«', '»', '“', '”'], '', preg_replace('/(?<![\p{L}\p{N}])(OOO|ООО)(?![\p{L}\p{N}])/ui', '', $company));

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Kompaniya tashuvchi yoki mijoz bo'lgan buyurtmalardan eng ko'p
     * qatnashgan OPERATION menejeri (har bir asosiy buyurtma bir marta) va INN.
     *
     * @param  array<int,array<string,mixed>>  $orders
     * @return array{operator:string,tin:?string}
     */
    public static function fromOrders(array $orders, string $company): array
    {
        $wanted = FillPlateOperatorsCommand::companySortKey($company);
        $perRoot = [];
        $tin = null;

        foreach ($orders as $order) {
            foreach (['carrier', 'customer'] as $side) {
                $party = $order[$side] ?? null;

                if (! $party || FillPlateOperatorsCommand::companySortKey((string) ($party['name'] ?? '')) !== $wanted) {
                    continue;
                }

                $tin ??= self::tin($party['tin'] ?? null);
                $operation = trim((string) ($order['operation']['name'] ?? ''));
                $root = $order['parent_order_id'] ?? $order['id'] ?? null;

                if ($operation !== '' && $root !== null) {
                    $perRoot[$root] ??= $operation;
                }
            }
        }

        $counts = array_count_values($perRoot);
        arsort($counts); // barqaror: teng sonlarda birinchi uchragani qoladi
        $operator = (string) (array_key_first($counts) ?? '');

        return ['operator' => self::cleanOperator($operator), 'tin' => $tin];
    }

    /**
     * @param  array<int,array<string,mixed>>  $contragents
     */
    public static function pickOperator(array $contragents, string $company): string
    {
        $match = self::matchContragent($contragents, $company);

        return self::cleanOperator((string) ($match['employee']['name'] ?? ''));
    }

    /**
     * @param  array<int,array<string,mixed>>  $contragents
     */
    public static function pickTin(array $contragents, string $company): ?string
    {
        return self::tin(self::matchContragent($contragents, $company)['tin'] ?? null);
    }

    /**
     * Nomi (yuridik shakl va qo'shtirnoqlarsiz) aynan mos kontragent, bo'lmasa birinchisi.
     *
     * @param  array<int,array<string,mixed>>  $contragents
     */
    private static function matchContragent(array $contragents, string $company): ?array
    {
        $wanted = FillPlateOperatorsCommand::companySortKey($company);

        return collect($contragents)->first(fn (array $c) => collect([$c['company_name'] ?? '', $c['name'] ?? '', $c['legal_name'] ?? ''])
            ->contains(fn ($name) => $name !== '' && FillPlateOperatorsCommand::companySortKey((string) $name) === $wanted)) ?? ($contragents[0] ?? null);
    }

    private static function cleanOperator(string $operator): string
    {
        $operator = trim($operator);

        return $operator === '' || mb_strtoupper($operator) === 'EGS OPERATION' ? self::EMPTY : $operator;
    }

    /** INN — 9 raqam (yakka tadbirkorda JShShIR — 14) */
    private static function tin(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return in_array(strlen($digits), [9, 14], true) ? $digits : null;
    }
}
