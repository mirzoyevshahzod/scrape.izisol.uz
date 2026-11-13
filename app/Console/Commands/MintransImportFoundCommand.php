<?php

namespace App\Console\Commands;

use App\Models\MintransFound;
use App\Models\MintransNotFound;
use App\Models\OrginfoInn;
use App\Support\UzbekPlates;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Qo'ldagi tayyor natija Excel'idan (odatda 3-bosqichdan keyingi
 * auto_import_template formati) topilgan raqamlarni mintrans_found
 * bazasiga yuklaydi — keyin 1-bosqich ularni Mintrans'dan qayta so'ramaydi.
 *
 * Ustunlar sarlavha bo'yicha topiladi (tartibi har xil bo'lishi mumkin):
 * "Davlat raqami" majburiy, qolgan 1-bosqich ustunlari (Rusumi, Korxona nomi,
 * ...) bo'lsa olinadi. Bitta raqamning bir nechta qatori — bir nechta litsenziya.
 * "инн" ustuni bo'lsa, "Korxona nomi" => INN orginfo_inns keshiga yoziladi
 * (3-bosqich ham orginfo.uz'ga qayta so'ramasin).
 *
 * Standart holatda bazada allaqachon bor (topilgan yoki "topilmadi" deb
 * yozilgan) raqamlarga tegmaydi; --overwrite bilan fayldagi ma'lumot ustun.
 */
class MintransImportFoundCommand extends Command
{
    protected $signature = 'mintrans:import-found
                            {file : Topilgan raqamlar Excel\'i ("Davlat raqami" ustuni bor, masalan 3-bosqich natijasi)}
                            {--overwrite : Bazada bor raqamlarni ham shu fayldagi ma\'lumot bilan almashtirish}
                            {--run= : Boshqaruv ishi (job_runs.id)}';

    protected $description = 'Tayyor Excel\'dagi topilgan avtoraqamlarni Mintrans bazasiga yuklaydi (1-bosqichda qayta so\'ralmasin)';

    private const PLATE_HEADER = 'Davlat raqami';

    private const NAME_HEADER = 'Korxona nomi';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Fayl topilmadi: {$file}");

            return self::FAILURE;
        }

        ini_set('memory_limit', '1024M');

        $this->info("Topilgan raqamlarni bazaga yuklash");

        // sarlavha => litsenziya kaliti (1-bosqich ustunlari)
        $keys = [];
        foreach (MintransScrapeCommand::COLUMNS as [$title, $key]) {
            $keys[mb_strtolower($title)] = $key;
        }

        $columns = null;  // ustun indeksi => kalit
        $innIndex = null;
        $rows = $badPlate = 0;
        $licenses = [];   // raqam => [litsenziya json => litsenziya]
        $inns = [];       // nom => [inn => true]

        foreach ($this->rows($file) as $cells) {
            if ($columns === null) {
                $columns = [];
                foreach ($cells as $index => $title) {
                    $title = mb_strtolower(trim($title));
                    if (isset($keys[$title])) {
                        $columns[$index] = $keys[$title];
                    } elseif ($title === mb_strtolower(MintransInnCommand::INN_HEADER)) {
                        $innIndex = $index;
                    }
                }

                if (! in_array('raqam', $columns, true)) {
                    $this->error('1-qatorda "' . self::PLATE_HEADER . '" sarlavhali ustun topilmadi.');

                    return self::FAILURE;
                }

                $this->line('Topilgan ustunlar: ' . count($columns) . ' ta' . ($innIndex !== null ? ' + инн' : ''));

                continue;
            }

            $license = [];
            foreach ($columns as $index => $key) {
                $value = trim($cells[$index] ?? '');
                if ($value !== '') {
                    $license[$key] = $value;
                }
            }

            if (! isset($license['raqam'])) {
                continue; // bo'sh qator
            }

            $rows++;
            $plate = UzbekPlates::extract($license['raqam'])[0] ?? null;

            if (! $plate) {
                $badPlate++;

                continue;
            }

            $license['raqam'] = $plate;
            ksort($license);
            $licenses[$plate][json_encode($license, JSON_UNESCAPED_UNICODE)] = $license;

            $inn = $innIndex !== null ? preg_replace('/\D/', '', $cells[$innIndex] ?? '') : '';
            if (strlen($inn) === 9 && isset($license['korxona'])) {
                $inns[$license['korxona']][$inn] = true;
            }
        }

        if ($columns === null) {
            $this->error('Fayl bo\'sh.');

            return self::FAILURE;
        }

        $plates = array_keys($licenses);
        $this->line("Ma'lumotli qatorlar: {$rows} ta; noyob raqamlar: " . count($plates) . ' ta'
            . ($badPlate ? "; raqami O'zbekiston avtoraqami emas — tashlab yuborildi: {$badPlate} ta" : ''));

        [$created, $updated, $skippedFound, $skippedMissing] = $this->store($licenses, $plates);

        $this->line("Bazaga yangi qo'shildi: {$created} ta");
        if ($updated) {
            $this->line("Bazada bor edi, yangilandi: {$updated} ta");
        }
        if ($skippedFound) {
            $this->line("Bazada allaqachon bor — tegilmadi: {$skippedFound} ta");
        }
        if ($skippedMissing) {
            $this->line("\"Topilmagan raqamlar\" ro'yxatida — tegilmadi: {$skippedMissing} ta (almashtirish uchun \"ustidan yozish\"ni belgilang)");
        }

        $innAdded = $this->storeInns($inns);
        if ($innIndex !== null) {
            $this->line("INN bazasiga qo'shildi (3-bosqich orginfo.uz'ga qayta so'ramaydi): {$innAdded} ta nom");
        }

        $this->info('Tayyor. Bazadagi topilgan raqamlar jami: ' . MintransFound::count() . ' ta');

        return self::SUCCESS;
    }

    /**
     * @param  array<string,array<string,array>>  $licenses
     * @return array{int,int,int,int} [yangi, yangilangan, bor (topilgan), topilmaganlar ro'yxatida]
     */
    private function store(array $licenses, array $plates): array
    {
        $overwrite = (bool) $this->option('overwrite');
        $existing = $missing = [];

        foreach (array_chunk($plates, 1000) as $chunk) {
            $existing += array_flip(MintransFound::whereIn('plate', $chunk)->pluck('plate')->all());
            // "xatolik" bilan yozilganlar to'smaydi — ular aniq topilmagan emas
            $missing += array_flip(MintransNotFound::whereIn('plate', $chunk)->where('status', MintransNotFound::NOT_FOUND)->pluck('plate')->all());
        }

        $created = $updated = $skippedFound = $skippedMissing = 0;
        $batch = $stored = [];
        $now = now();

        foreach ($licenses as $plate => $rows) {
            if (! $overwrite && isset($existing[$plate])) {
                $skippedFound++;

                continue;
            }

            if (! $overwrite && isset($missing[$plate])) {
                $skippedMissing++;

                continue;
            }

            isset($existing[$plate]) ? $updated++ : $created++;
            $stored[] = $plate;
            $batch[] = [
                'plate' => $plate,
                'licenses' => json_encode(array_values($rows), JSON_UNESCAPED_UNICODE),
                'job_run_id' => $this->option('run') ?: null,
                'checks' => 1,
                'checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 500) {
                $this->flush($batch);
                $batch = [];
            }
        }

        $this->flush($batch);

        // yuklangan raqamlar endi topilgan deb hisoblanadi — "topilmaganlar"dan (xatolik yoki ustidan yozilgan) chiqadi
        foreach (array_chunk($stored, 1000) as $chunk) {
            MintransNotFound::whereIn('plate', $chunk)->delete();
        }

        return [$created, $updated, $skippedFound, $skippedMissing];
    }

    private function flush(array $batch): void
    {
        if ($batch) {
            MintransFound::upsert($batch, ['plate'], ['licenses', 'job_run_id', 'checked_at', 'updated_at']);
        }
    }

    /**
     * Nom => INN (faqat bir xil INN'li, yashirilmagan nomlar). Bazada bor
     * nomga tegmaydi, faqat "topilmadi" deb yozilganini to'ldiradi.
     */
    private function storeInns(array $inns): int
    {
        $added = 0;

        foreach ($inns as $name => $values) {
            $name = trim($name);

            // "OOO "X******* M*******"" — har xil korxonalar bir xil ko'rinadi; bir nechta INN — noaniq
            if ($name === '' || str_contains($name, '*') || count($values) !== 1) {
                continue;
            }

            $inn = (string) array_key_first($values);
            $row = OrginfoInn::firstOrNew(['name' => $name]);

            if ($row->exists && $row->inn !== null) {
                continue;
            }

            $row->fill(['inn' => $inn, 'checked_at' => now()])->save();
            $added++;
        }

        return $added;
    }

    /**
     * Faylning qatorlari (har biri — kataklar matni ro'yxati). .xlsx oqim bilan
     * (OpenSpout) o'qiladi, katta fayl ham xotiraga to'liq yuklanmaydi.
     *
     * @return \Generator<int,list<string>>
     */
    private function rows(string $file): \Generator
    {
        $text = fn ($value) => match (true) {
            $value instanceof \DateTimeInterface => $value->format('d.m.Y'),
            is_float($value) && floor($value) === $value => (string) (int) $value,
            default => (string) $value,
        };

        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'xlsx') {
            foreach (IOFactory::load($file)->getActiveSheet()->toArray(null, false, true, false) as $row) {
                yield array_map(fn ($v) => (string) $v, $row);
            }

            return;
        }

        $reader = new XlsxReader();
        $reader->open($file);

        try {
            $sheets = iterator_to_array($reader->getSheetIterator(), false);
            $sheet = collect($sheets)->first(fn ($s) => $s->isActive()) ?? $sheets[0];

            foreach ($sheet->getRowIterator() as $row) {
                yield array_map(fn ($cell) => $text($cell->getValue()), $row->getCells());
            }
        } finally {
            $reader->close();
        }
    }
}
