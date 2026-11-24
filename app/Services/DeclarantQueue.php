<?php

namespace App\Services;

use App\Models\Auto;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * belarusborder.by `info/monitoring-new` javobi (Belarus kutish zonalari
 * navbati) dan o'zbek yuk mashinalari Excel'ini tayyorlaydi:
 * storage/app/declarant/{zona}-{sana}.xlsx — crm:daily-import (Declarant)
 * va Telegram'ga yuborish shu fayllarni oladi.
 *
 * Javob ikki yo'l bilan keladi: to'g'ridan-to'g'ri API (scrape:declarant-data —
 * sayt Cloudflare Turnstile qo'ygandan beri bo'sh qaytadi) yoki captcha'ni
 * odam yechgan brauzer orqali (deploy/belarus-queue-browser kengaytmasi ->
 * POST /api/belarus-queue -> belarus:queue-sync).
 */
class DeclarantQueue
{
    public const ZONES = [
        'benyakoni' => ['checkpoint_id' => '53d94097-2b34-11ec-8467-ac1f6bf889c0', 'name' => 'Бенякони'],
        'kamennii-log' => ['checkpoint_id' => 'b60677d4-8a00-4f93-a781-e129e1692a03', 'name' => 'Каменный Лог'],
        'kozlovichi' => ['checkpoint_id' => '98b5be92-d3a5-4ba2-9106-76eb4eb3df49', 'name' => 'Козловичи'],
    ];

    private const UZBEK_PATTERNS = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{4}[A-Z]{2}$/',
    ];

    private const HEADERS = [
        'Порядок вызова', 'Тип очереди', 'Рег.номер', 'Дата регистрации в ЗО', 'Статус изменен',
        'Статус', 'Company Name', 'Phone', 'State', 'Border',
    ];

    private const STATUSES = [1 => 'В очереди', 2 => 'Прибыл в ЗО', 3 => 'Вызван в ПП'];

    private const QUEUE_TYPES = [1 => 'Приоритет', 2 => 'Электронная очередь', 3 => 'Живая очередь'];

    public static function zoneByCheckpoint(mixed $checkpointId): ?string
    {
        foreach (self::ZONES as $zone => $info) {
            if ($info['checkpoint_id'] === $checkpointId) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Javobda hech bo'lmasa bitta navbat ro'yxati to'la-mi. Captcha'siz
     * so'rovga sayt `info`ni beradi-yu, barcha ro'yxatlar bo'sh — bunday
     * javobdan fayl yozilmaydi (oxirgi yaxshi fayl o'rnida qolsin).
     */
    public static function hasData(array $data): bool
    {
        foreach ($data as $key => $value) {
            if ($key !== 'info' && is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{path: string, queue: int, rows: int} queue — navbatdagi yuk mashinalari, rows — o'zbeklari
     */
    public function export(string $zone, array $data): array
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(self::HEADERS);

        $queue = $data['truckLiveQueue'] ?? [];
        $trucks = [];

        foreach ($queue as $item) {
            $regnum = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($item['regnum'] ?? '')));

            if (self::isUzbek($regnum)) {
                $trucks[] = [$regnum, $item];
            }
        }

        // korxona nomi va telefon — bazadagi avtomobillardan, bitta so'rov bilan
        $autos = Auto::whereIn('state_number', array_column($trucks, 0))->get()->keyBy('state_number');
        $row = 2;

        foreach ($trucks as [$regnum, $item]) {
            $auto = $autos[$regnum] ?? null;

            $sheet->setCellValue("A{$row}", $item['order_id'] ?? '-');
            $sheet->setCellValue("B{$row}", self::QUEUE_TYPES[$item['type_queue'] ?? null] ?? ($item['type_queue'] ?? ''));
            $sheet->setCellValue("C{$row}", $regnum);
            $sheet->setCellValue("D{$row}", $item['registration_date'] ?? '');
            $sheet->setCellValue("E{$row}", $item['changed_date'] ?? '');
            $sheet->setCellValue("F{$row}", self::STATUSES[$item['status'] ?? null] ?? ($item['status'] ?? ''));
            $sheet->setCellValue("G{$row}", $auto->company_name ?? '');
            $sheet->setCellValueExplicit("H{$row}", (string) ($auto->phone ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("I{$row}", 'Беларусь Литва');
            $sheet->setCellValue("J{$row}", self::ZONES[$zone]['name']);
            $row++;
        }

        $dir = storage_path('app/declarant');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = "{$dir}/{$zone}-" . now()->format('Y-m-d-H-i-s') . '.xlsx';
        (new Xlsx($book))->save($path);

        return ['path' => $path, 'queue' => count($queue), 'rows' => $row - 2];
    }

    private static function isUzbek(string $regnum): bool
    {
        foreach (self::UZBEK_PATTERNS as $pattern) {
            if (preg_match($pattern, $regnum)) {
                return true;
            }
        }

        return false;
    }
}
