<?php

namespace App\Http\Controllers;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * "Boshqaruv" sahifasi: config/operations.php'dagi amallarni queue orqali
 * ishga tushirish va ularning holatini kuzatish. Har bir amal o'z
 * permission'i (operation.*) bilan himoyalangan.
 */
class OperationController extends Controller
{
    public function index(Request $request): View
    {
        return view('operations.index', [
            'operations' => $this->allowedOperations($request),
        ]);
    }

    public function run(Request $request, string $operation): JsonResponse
    {
        $config = config("operations.operations.{$operation}");

        abort_unless($config, 404);
        abort_unless($request->user()->can($config['permission']), 403);

        // Kun so'raladigan amallar (config: 'date' => true) uchun sana majburiy
        $params = [];

        if (! empty($config['date'])) {
            $params['date'] = $request->validate([
                'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            ], [
                'date.required' => 'Kunni tanlang.',
                'date.date_format' => 'Sana YYYY-MM-DD formatida bo\'lishi kerak.',
                'date.before_or_equal' => 'Kelajakdagi kunni tanlab bo\'lmaydi.',
            ])['date'];
        }

        // Tugma ikki marta tez bosilsa ham bitta amal ikki marta navbatga
        // tushmasligi uchun tekshiruv va yaratish qulf ichida bajariladi.
        $run = Cache::lock("operations:run:{$operation}", 10)->block(5, function () use ($operation, $request, $params) {
            if (JobRun::where('operation', $operation)->active()->exists()) {
                return null;
            }

            return JobRun::create([
                'operation' => $operation,
                'status' => JobRun::QUEUED,
                'user_id' => $request->user()->id,
                'params' => $params ?: null,
            ]);
        });

        if (! $run) {
            return response()->json(['message' => "\"{$config['label']}\" allaqachon navbatda yoki ishlayapti."], 409);
        }

        RunOperationJob::dispatch($run);

        return response()->json(['message' => "\"{$config['label']}\" navbatga qo'shildi.", 'run' => $this->present($run)], 202);
    }

    /** Sahifa har necha soniyada chaqiradi: oxirgi ishlar va har bir amalning holati */
    public function runs(Request $request): JsonResponse
    {
        $allowed = array_keys($this->allowedOperations($request));

        $runs = JobRun::with('user:id,name')
            ->whereIn('operation', $allowed)
            ->latest('id')
            ->limit(20)
            ->get();

        $latest = [];

        foreach ($allowed as $operation) {
            $last = JobRun::with('user:id,name')->where('operation', $operation)->latest('id')->first();
            $latest[$operation] = $last ? $this->present($last) : null;
        }

        return response()->json([
            'runs' => $runs->map(fn ($run) => $this->present($run)),
            'latest' => $latest,
        ]);
    }

    public function show(Request $request, JobRun $run): JsonResponse
    {
        abort_unless(array_key_exists($run->operation, $this->allowedOperations($request)), 403);

        return response()->json($this->present($run) + ['output' => $run->output ?? '']);
    }

    /** @return array<string,array> foydalanuvchi ishga tushira oladigan amallar */
    private function allowedOperations(Request $request): array
    {
        return array_filter(
            config('operations.operations'),
            fn ($config) => $request->user()->can($config['permission'])
        );
    }

    private function present(JobRun $run): array
    {
        $isActive = in_array($run->status, [JobRun::QUEUED, JobRun::RUNNING], true);
        $stale = $isActive && $run->updated_at?->lt(now()->subMinutes(JobRun::STALE_AFTER_MINUTES));

        return [
            'id' => $run->id,
            'operation' => $run->operation,
            'label' => config("operations.operations.{$run->operation}.label", $run->operation),
            'date' => $run->params['date'] ?? null,
            'status' => $stale ? 'stale' : $run->status,
            'active' => $isActive && ! $stale,
            'user' => $run->user?->name,
            'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
            'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
            'duration' => $run->started_at ? $run->started_at->diffInSeconds($run->finished_at ?? now(), true) : null,
            'queued_seconds' => $run->status === JobRun::QUEUED ? (int) $run->created_at->diffInSeconds(now(), true) : null,
        ];
    }
}
