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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mintrans Avtoraqam sahifasi (permission: page.mintrans-auto).
 *
 * Yuklangan Excel "mintrans" amali sifatida queue'ga qo'yiladi
 * (config/operations.php, RunOperationJob):
 *   1-bosqich mintrans:scrape       — Mintrans'dan ma'lumot, topilmaganlar bazaga;
 *   2-bosqich mintrans:clean-names  — korxona nomlarini OOO "NOMI" ko'rinishiga.
 */
class MintransController extends Controller
{
    private const OPERATION = 'mintrans';

    public function index(): View
    {
        return view('mintrans.index', [
            'missingCount' => MintransNotFound::where('status', MintransNotFound::NOT_FOUND)->count(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'skip_missing' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Excel faylni tanlang.',
            'file.mimes' => 'Faqat .xlsx, .xls yoki .csv fayl.',
            'file.max' => 'Fayl 10 MB dan oshmasin.',
        ]);

        $upload = $request->file('file');
        // nom foydalanuvchidan emas — uuid + fayl mazmuniga qarab aniqlangan kengaytma
        $name = Str::uuid() . '.' . ($upload->guessExtension() ?: 'xlsx');
        $dir = storage_path('app/mintrans/uploads');
        $upload->move($dir, $name);

        $run = Cache::lock('operations:run:' . self::OPERATION, 10)->block(5, function () use ($request, $upload, $dir, $name) {
            if (JobRun::where('operation', self::OPERATION)->active()->exists()) {
                return null;
            }

            return JobRun::create([
                'operation' => self::OPERATION,
                'status' => JobRun::QUEUED,
                'user_id' => $request->user()->id,
                'params' => [
                    'file' => "{$dir}/{$name}",
                    'original_name' => mb_substr($upload->getClientOriginalName(), 0, 150),
                    'result' => storage_path('app/mintrans/results/mintrans_result_' . now()->format('Y_m_d_H_i_s') . '.xlsx'),
                    'skip_missing' => $request->boolean('skip_missing'),
                ],
            ]);
        });

        if (! $run) {
            @unlink("{$dir}/{$name}");

            return response()->json(['message' => 'Mintrans tekshiruvi allaqachon ishlayapti — tugashini kuting.'], 409);
        }

        RunOperationJob::dispatch($run);

        return response()->json(['message' => 'Fayl qabul qilindi, tekshiruv navbatga qo\'shildi.', 'run' => $this->present($run)], 202);
    }

    public function runs(): JsonResponse
    {
        $runs = JobRun::with('user:id,name')->where('operation', self::OPERATION)->latest('id')->limit(20)->get();

        return response()->json(['runs' => $runs->map(fn ($run) => $this->present($run))]);
    }

    public function show(JobRun $run): JsonResponse
    {
        abort_unless($run->operation === self::OPERATION, 404);

        return response()->json($this->present($run) + ['output' => $run->output ?? '']);
    }

    public function download(JobRun $run): BinaryFileResponse
    {
        abort_unless($run->operation === self::OPERATION, 404);

        $file = $run->params['result'] ?? null;
        abort_unless($file && is_file($file), 404, 'Natija fayli hali tayyor emas.');

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
            'file' => $run->params['original_name'] ?? '—',
            'skip_missing' => (bool) ($run->params['skip_missing'] ?? false),
            'status' => $stale ? 'stale' : $run->status,
            'active' => $isActive && ! $stale,
            'progress' => $run->params['progress'] ?? null,
            'downloadable' => ! $isActive && $result && is_file($result),
            'user' => $run->user?->name,
            'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
            'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
            'duration' => $run->started_at ? $run->started_at->diffInSeconds($run->finished_at ?? now(), true) : null,
            'queued_seconds' => $run->status === JobRun::QUEUED ? (int) $run->created_at->diffInSeconds(now(), true) : null,
        ];
    }
}
