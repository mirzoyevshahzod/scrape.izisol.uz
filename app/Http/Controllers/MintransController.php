<?php

namespace App\Http\Controllers;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use App\Models\MintransNotFound;
use App\Services\MintransClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mintrans Avtoraqam sahifasi (permission: page.mintrans-auto).
 *
 * Yuklangan Excel queue'ga qo'yiladi (config/operations.php, RunOperationJob):
 *   "mintrans"        — 1 -> 2 -> 3: mintrans:scrape (Mintrans'dan ma'lumot,
 *                       topilmaganlar bazaga), mintrans:clean-names (nomlar
 *                       OOO "NOMI"), mintrans:inn (orginfo.uz'dan INN);
 *   "mintrans-names"  — 2 -> 3: ma'lumoti bor Excel'da nomlar + INN;
 *   "mintrans-inn"    — faqat 3: nomlari formatlangan Excel'ga INN.
 */
class MintransController extends Controller
{
    /** bosqich => amal (config/operations.php) */
    private const OPERATIONS = ['full' => 'mintrans', 'names' => 'mintrans-names', 'inn' => 'mintrans-inn'];

    /** natija fayli nomiga qo'shimcha (2- va 3-bosqichdan boshlanganda) */
    private const SUFFIXES = ['names' => '_formatlangan', 'inn' => '_inn'];

    /** "Natija" oynasi har 2 s so'raydi — butun log (1 MB gacha) emas, oxirgi qismi */
    private const OUTPUT_TAIL_BYTES = 300_000;

    /** Ilovaning o'z chegarasi (validatsiya: max:10240) */
    private const MAX_UPLOAD_MB = 10;

    public function index(): View
    {
        return view('mintrans.index', [
            'maxUploadMb' => self::effectiveUploadLimitMb(),
            'missingCount' => MintransNotFound::where('status', MintransNotFound::NOT_FOUND)->count(),
            'missingErrors' => MintransNotFound::where('status', MintransNotFound::ERROR)->count(),
            'recoveredTotal' => $this->recoveredTotal(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'stage' => ['nullable', 'in:full,names,inn'],
            'skip_missing' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Excel faylni tanlang.',
            'file.mimes' => 'Faqat .xlsx, .xls yoki .csv fayl.',
            'file.max' => 'Fayl 10 MB dan oshmasin.',
            // PHP faylni umuman qabul qilmagan (odatda upload_max_filesize / post_max_size'dan katta)
            'file.uploaded' => 'Fayl serverga yuklanmadi: hajmi serverning PHP chegarasidan katta ('
                . self::effectiveUploadLimitMb() . ' MB). Administrator PHP\'da upload_max_filesize va post_max_size\'ni oshirishi kerak.',
        ]);

        $stage = $request->input('stage', 'full');
        $operation = self::OPERATIONS[$stage];
        $upload = $request->file('file');

        // 2- yoki 3-bosqichdan: "Korxona nomi" ustuni bo'lmasa — navbatga qo'ymasdan darhol aytamiz
        if ($stage !== 'full' && ! $this->hasCompanyColumn($upload->getRealPath())) {
            return response()->json([
                'message' => 'Faylda "Korxona nomi" ustuni topilmadi.',
                'errors' => ['file' => ['Faylning 1-qatorida "Korxona nomi" sarlavhali ustun bo\'lishi kerak.']],
            ], 422);
        }

        // nom foydalanuvchidan emas — uuid + fayl mazmuniga qarab aniqlangan kengaytma
        $name = Str::uuid() . '.' . ($upload->guessExtension() ?: 'xlsx');
        $dir = storage_path('app/mintrans/uploads');
        $upload->move($dir, $name);

        $originalName = mb_substr($upload->getClientOriginalName(), 0, 150);
        $resultDir = storage_path('app/mintrans/results');

        $params = $stage !== 'full'
            ? [
                'file' => "{$dir}/{$name}",
                'original_name' => $originalName,
                'result' => "{$resultDir}/" . Str::uuid() . '.xlsx',
                // yuklab olinganda: "<asl nom>_formatlangan.xlsx" / "<asl nom>_inn.xlsx"
                'download_name' => $this->safeBaseName($originalName) . self::SUFFIXES[$stage] . '.xlsx',
            ]
            : [
                'file' => "{$dir}/{$name}",
                'original_name' => $originalName,
                'result' => "{$resultDir}/mintrans_result_" . now()->format('Y_m_d_H_i_s') . '.xlsx',
                // 1-bosqich A ustundan ajratgan avtoraqamlar ro'yxati (so'rovlardan oldin yoziladi)
                'plates' => "{$resultDir}/raqamlar_" . now()->format('Y_m_d_H_i_s') . '.xlsx',
                'skip_missing' => $request->boolean('skip_missing'),
            ];

        $run = $this->queueRun($request, $operation, $params);

        if (! $run) {
            @unlink("{$dir}/{$name}");

            return response()->json(['message' => match ($stage) {
                'names' => 'Nomlarni formatlash allaqachon ishlayapti — tugashini kuting.',
                'inn' => 'INN qo\'shish allaqachon ishlayapti — tugashini kuting.',
                default => 'Mintrans tekshiruvi allaqachon ishlayapti — tugashini kuting.',
            }], 409);
        }

        RunOperationJob::dispatch($run);

        return response()->json([
            'message' => match ($stage) {
                'names' => 'Fayl qabul qilindi: nomlar formatlanadi va INN qo\'shiladi.',
                'inn' => 'Fayl qabul qilindi: orginfo.uz\'dan INN qo\'shilmoqda.',
                default => 'Fayl qabul qilindi, tekshiruv navbatga qo\'shildi.',
            },
            'run' => $this->present($run),
        ], 202);
    }

    /**
     * "Topilmagan raqamlar" bazasidagi barcha raqamlarni (topilmadi va xatolik)
     * odatdagi to'liq ish sifatida (1 -> 2 -> 3) qayta tekshiradi: ma'lumoti
     * chiqqanlari ro'yxatdan chiqadi va natija Excel'iga yoziladi.
     */
    public function recheckMissing(Request $request): JsonResponse
    {
        $plates = MintransNotFound::orderBy('plate')->pluck('plate')->all();

        if (! $plates) {
            return response()->json(['message' => 'Topilmagan raqamlar ro\'yxati bo\'sh — tekshiradigan narsa yo\'q.'], 422);
        }

        // 1-bosqich A ustundan o'qiydi — ro'yxat oddiy yuklangan fayl kabi Excel'ga yoziladi
        $dir = storage_path('app/mintrans/uploads');
        $file = "{$dir}/" . Str::uuid() . '.xlsx';
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        foreach ($plates as $i => $plate) {
            $sheet->setCellValueExplicit('A' . ($i + 1), $plate, DataType::TYPE_STRING);
        }
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        (new Xlsx($book))->save($file);

        $resultDir = storage_path('app/mintrans/results');
        $run = $this->queueRun($request, self::OPERATIONS['full'], [
            'file' => $file,
            'original_name' => 'Topilmaganlar (' . count($plates) . ' ta)',
            'source' => 'missing',
            'result' => "{$resultDir}/mintrans_topilmaganlar_" . now()->format('Y_m_d_H_i_s') . '.xlsx',
            'plates' => "{$resultDir}/raqamlar_" . now()->format('Y_m_d_H_i_s') . '.xlsx',
            'skip_missing' => false,
        ]);

        if (! $run) {
            @unlink($file);

            return response()->json(['message' => 'Mintrans tekshiruvi allaqachon ishlayapti — tugashini kuting.'], 409);
        }

        RunOperationJob::dispatch($run);

        return response()->json([
            'message' => count($plates) . ' ta topilmagan raqam qayta tekshirish uchun navbatga qo\'shildi.',
            'run' => $this->present($run),
        ], 202);
    }

    /**
     * To'xtab qolgan to'liq ishni (1 -> 2 -> 3) shu yozuvning o'zida davom ettiradi:
     * 1-bosqich natija faylida yoki "topilmadi" ro'yxatida bo'lmagan raqamlardan
     * boshlaydi va qatorlarni fayl oxiriga qo'shadi; 2- va 3-bosqich butun
     * fayl bo'yicha qaytadan o'tadi (formatlash takrorlansa o'zgarmaydi, INN'lar bazada).
     */
    public function resume(Request $request, JobRun $run): JsonResponse
    {
        abort_unless($run->operation === self::OPERATIONS['full'], 404);

        if (! $this->resumable($run)) {
            return response()->json(['message' => 'Bu ishni davom ettirib bo\'lmaydi: u tugallanmagan yoki fayllari topilmadi.'], 422);
        }

        $resumed = Cache::lock("operations:run:{$run->operation}", 10)->block(5, function () use ($run) {
            if (JobRun::where('operation', $run->operation)->active()->exists()) {
                return false;
            }

            $params = $run->params;
            $params['resume'] = true;
            unset($params['inn']);

            $run->appendOutput("\n— Davom ettirildi [" . now()->format('Y-m-d H:i:s') . "] —\n");
            $run->update(['status' => JobRun::QUEUED, 'params' => $params, 'finished_at' => null]);

            return true;
        });

        if (! $resumed) {
            return response()->json(['message' => 'Mintrans tekshiruvi allaqachon ishlayapti — tugashini kuting.'], 409);
        }

        RunOperationJob::dispatch($run);

        return response()->json([
            'message' => "#{$run->id} davom ettirilmoqda: tekshirilmagan raqamlardan boshlanadi.",
            'run' => $this->present($run->fresh()),
        ], 202);
    }

    public function runs(): JsonResponse
    {
        $runs = JobRun::with('user:id,name')->whereIn('operation', self::OPERATIONS)->latest('id')->limit(20)->get();

        return response()->json(['runs' => $runs->map(fn ($run) => $this->present($run))]);
    }

    public function show(JobRun $run): JsonResponse
    {
        abort_unless(in_array($run->operation, self::OPERATIONS, true), 404);

        return response()->json($this->present($run) + ['output' => $run->outputTail(self::OUTPUT_TAIL_BYTES)]);
    }

    public function download(JobRun $run): BinaryFileResponse
    {
        abort_unless(in_array($run->operation, self::OPERATIONS, true), 404);

        $file = $run->params['result'] ?? null;
        abort_unless($file && is_file($file), 404, 'Natija fayli hali tayyor emas.');

        return response()->download($file, $run->params['download_name'] ?? basename($file));
    }

    /** 1-bosqich ajratgan avtoraqamlar ro'yxati — ish davomida ham yuklab olinadi */
    public function plates(JobRun $run): BinaryFileResponse
    {
        abort_unless(in_array($run->operation, self::OPERATIONS, true), 404);

        $file = $run->params['plates'] ?? null;
        abort_unless($file && is_file($file), 404, 'Raqamlar ro\'yxati hali tayyor emas.');

        return response()->download($file, basename($file));
    }

    public function ping(MintransClient $client): JsonResponse
    {
        return response()->json($client->ping());
    }

    /** Topilmagan raqamlar ro'yxati (qidiruv bilan, sahifalab) */
    public function missing(Request $request): JsonResponse
    {
        $search = strtoupper(preg_replace('/[\s-]+/', '', (string) $request->query('q')));

        $page = MintransNotFound::query()
            ->when($search !== '', fn ($q) => $q->where('plate', 'like', "%{$search}%"))
            ->orderByDesc('checked_at')
            ->paginate(15);

        return response()->json([
            'total' => MintransNotFound::where('status', MintransNotFound::NOT_FOUND)->count(),
            'errors' => MintransNotFound::where('status', MintransNotFound::ERROR)->count(),
            'recovered' => $this->recoveredTotal(),
            'items' => collect($page->items())->map(fn ($row) => [
                'plate' => $row->plate,
                'status' => $row->status,
                'message' => $row->message,
                'checks' => $row->checks,
                'checked_at' => $row->checked_at->format('Y-m-d H:i'),
            ]),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function exportMissing(): StreamedResponse
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['Avtoraqam', 'Holati', 'Xato matni', 'Tekshirilgan (marta)', 'Oxirgi tekshiruv'], null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;
        MintransNotFound::orderBy('plate')->each(function ($item) use ($sheet, &$row) {
            $sheet->setCellValueExplicit("A{$row}", $item->plate, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $item->status === MintransNotFound::NOT_FOUND ? 'Topilmadi' : 'Xatolik');
            $sheet->setCellValue("C{$row}", $item->message);
            $sheet->setCellValue("D{$row}", $item->checks);
            $sheet->setCellValue("E{$row}", $item->checked_at->format('Y-m-d H:i'));
            $row++;
        });

        foreach (['A' => 16, 'B' => 12, 'C' => 50, 'D' => 20, 'E' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            'mintrans_topilmagan_' . now()->format('Y_m_d_H_i') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function present(JobRun $run): array
    {
        $isActive = in_array($run->status, [JobRun::QUEUED, JobRun::RUNNING], true);
        $stale = $isActive && $run->updated_at?->lt(now()->subMinutes(JobRun::STALE_AFTER_MINUTES));
        $result = $run->params['result'] ?? null;

        return [
            'id' => $run->id,
            'stage' => array_search($run->operation, self::OPERATIONS, true) ?: 'full',
            'file' => $run->params['original_name'] ?? '—',
            'skip_missing' => (bool) ($run->params['skip_missing'] ?? false),
            'from_missing' => ($run->params['source'] ?? null) === 'missing',
            'status' => $stale ? 'stale' : $run->status,
            'active' => $isActive && ! $stale,
            'progress' => $run->params['progress'] ?? null,
            'inn' => $run->params['inn'] ?? null,
            'downloadable' => ! $isActive && $result && is_file($result),
            'plates_ready' => ! empty($run->params['plates']) && is_file($run->params['plates']),
            'resumable' => $this->resumable($run),
            'user' => $run->user?->name,
            'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
            'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
            'duration' => $run->started_at ? $run->started_at->diffInSeconds($run->finished_at ?? now(), true) : null,
            'queued_seconds' => $run->status === JobRun::QUEUED ? (int) $run->created_at->diffInSeconds(now(), true) : null,
        ];
    }

    /** Barcha to'liq ishlarda "topilmaganlar" ro'yxatidan chiqib, ma'lumoti topilgan raqamlar soni */
    private function recoveredTotal(): int
    {
        return (int) JobRun::where('operation', self::OPERATIONS['full'])->pluck('params')
            ->sum(fn ($params) => $params['progress']['recovered'] ?? 0);
    }

    /** Shu amal ishlamayotgan bo'lsa yangi ish yaratadi, aks holda null */
    private function queueRun(Request $request, string $operation, array $params): ?JobRun
    {
        return Cache::lock("operations:run:{$operation}", 10)->block(5, function () use ($request, $operation, $params) {
            if (JobRun::where('operation', $operation)->active()->exists()) {
                return null;
            }

            return JobRun::create([
                'operation' => $operation,
                'status' => JobRun::QUEUED,
                'user_id' => $request->user()->id,
                'params' => $params,
            ]);
        });
    }

    /** Xato bilan to'xtagan (yoki osilib qolgan) to'liq ish — yuklangan fayl va natija fayli joyida */
    private function resumable(JobRun $run): bool
    {
        $stale = in_array($run->status, [JobRun::QUEUED, JobRun::RUNNING], true)
            && $run->updated_at?->lt(now()->subMinutes(JobRun::STALE_AFTER_MINUTES));

        return $run->operation === self::OPERATIONS['full']
            && ($run->status === JobRun::FAILED || $stale)
            && is_file($run->params['file'] ?? '')
            && is_file($run->params['result'] ?? '');
    }

    private function hasCompanyColumn(string $path): bool
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, false, false)[0] ?? [];

            return in_array('Korxona nomi', array_map(fn ($v) => trim((string) $v), $header), true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Yuklab olinadigan fayl nomi uchun: "mintrans_result 28.10.xlsx" -> "mintrans_result_28_10" */
    private function safeBaseName(string $original): string
    {
        $base = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', pathinfo($original, PATHINFO_FILENAME));

        return trim(mb_substr($base, 0, 100), '_') ?: 'natija';
    }

    /**
     * Haqiqatda yuklash mumkin bo'lgan eng katta hajm (MB): ilova chegarasi va
     * PHP'ning upload_max_filesize / post_max_size qiymatlarining eng kichigi.
     */
    public static function effectiveUploadLimitMb(): float
    {
        $toMb = function (string $value): float {
            $value = trim($value);
            $number = (float) $value;

            return match (strtoupper(substr($value, -1))) {
                'G' => $number * 1024,
                'M' => $number,
                'K' => $number / 1024,
                default => $number / 1048576,
            };
        };

        $limits = [self::MAX_UPLOAD_MB];

        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $value = (string) ini_get($key);

            // 0 yoki bo'sh — PHP'da cheklov yo'q
            if ($value !== '' && $toMb($value) > 0) {
                $limits[] = $toMb($value);
            }
        }

        return round(min($limits), 1);
    }
}
