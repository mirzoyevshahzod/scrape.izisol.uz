<?php

namespace App\Http\Controllers;

use App\Jobs\RunOperationJob;
use App\Models\JobRun;
use App\Models\SpotEvent;
use App\Models\SpotEventKeyword;
use App\Services\SpotEventsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * "Spot.uz tadbirlari" sahifasi: spot:events yig'gan tadbirlar ro'yxati,
 * kalit so'zlarni qo'shish/o'chirish va "Qidirish" tugmasi — spot:events'ni
 * jadvalni kutmasdan hozir ishga tushiradi (queue orqali,
 * config/operations.php'dagi yashirin amal).
 */
class SpotEventController extends Controller
{
    public const OPERATION = 'spot-events';

    private const FILTERS = ['all', 'matched', 'sent'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $today = Carbon::today()->toDateString();
        $past = 'CASE WHEN ends_on IS NOT NULL AND ends_on < ? THEN 1 ELSE 0 END';

        $events = SpotEvent::query()
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%' . addcslashes($q, '%_\\') . '%'))
            ->when($filter === 'matched', fn ($query) => $query->whereNotNull('matched_keyword'))
            ->when($filter === 'sent', fn ($query) => $query->whereNotNull('notified_at'))
            // avval bo'lajak tadbirlar (eng yaqini birinchi), keyin o'tib ketganlar (eng yangisi birinchi)
            ->orderByRaw($past, [$today])
            ->orderByRaw("CASE WHEN {$past} = 0 THEN starts_on END ASC", [$today])
            ->orderByDesc('starts_on')
            ->paginate(25)
            ->withQueryString();

        return view('spot-events.index', [
            'events' => $events,
            'q' => $q,
            'filter' => $filter,
            'stats' => [
                'total' => SpotEvent::count(),
                'upcoming' => SpotEvent::whereNotNull('matched_keyword')
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))->count(),
                'sent' => SpotEvent::whereNotNull('notified_at')->count(),
            ],
            'keywords' => SpotEventKeyword::orderBy('id')->get(),
            'chatCount' => count(config('spot_events.chat_ids')),
            'lastRun' => $this->latestRun(),
        ]);
    }

    public function storeKeyword(Request $request): RedirectResponse
    {
        $keyword = trim(preg_replace('/\s+/u', ' ', (string) $request->input('keyword')));
        $request->merge(['keyword' => $keyword]);

        $request->validateWithBag('keyword', [
            'keyword' => ['required', 'string', 'min:2', 'max:100', function ($attribute, $value, $fail) {
                $normalized = SpotEventsClient::normalizeForMatch($value);

                if (SpotEventKeyword::pluck('keyword')->contains(fn ($k) => SpotEventsClient::normalizeForMatch($k) === $normalized)) {
                    $fail('Bu kalit so\'z allaqachon bor.');
                }
            }],
        ], [
            'keyword.required' => 'Kalit so\'zni yozing.',
            'keyword.min' => 'Kalit so\'z kamida 2 ta belgi bo\'lsin.',
            'keyword.max' => 'Kalit so\'z 100 ta belgidan oshmasin.',
        ]);

        SpotEventKeyword::create(['keyword' => $keyword]);
        $this->rematch();

        // shu so'zga mos, hali yuborilmagan va o'tib ketmagan tadbirlar — keyingi qidiruvda ketadi
        $pending = SpotEvent::whereNull('notified_at')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', Carbon::today()))
            ->get()
            ->filter(fn (SpotEvent $event) => SpotEventsClient::matchKeyword($event->title, [$keyword]))
            ->count();

        return redirect()->route('spot-events.index')->with('status', "\"{$keyword}\" qo'shildi."
            . ($pending ? " Unga mos {$pending} ta yangi tadbir bor — keyingi qidiruvda (yoki \"Qidirish\" bosilsa) Telegram'ga yuboriladi." : ''));
    }

    public function destroyKeyword(SpotEventKeyword $keyword): RedirectResponse
    {
        $keyword->delete();
        $this->rematch();

        return redirect()->route('spot-events.index')->with('status', "\"{$keyword->keyword}\" o'chirildi.");
    }

    /**
     * Kalit so'zlar o'zgargach, bazadagi tadbirlarning mosligini qayta hisoblaydi
     * (sahifa darhol to'g'ri ko'rsatsin; yuborish baribir spot:events'da).
     */
    private function rematch(): void
    {
        $keywords = SpotEventKeyword::list();

        SpotEvent::query()->each(function (SpotEvent $event) use ($keywords) {
            $event->matched_keyword = SpotEventsClient::matchKeyword($event->title, $keywords);
            $event->save();
        });
    }

    public function run(Request $request): JsonResponse
    {
        // Tugma ikki marta tez bosilsa ham ish bir marta navbatga tushadi
        $run = Cache::lock('operations:run:' . self::OPERATION, 10)->block(5, function () use ($request) {
            if (JobRun::where('operation', self::OPERATION)->active()->exists()) {
                return null;
            }

            return JobRun::create([
                'operation' => self::OPERATION,
                'status' => JobRun::QUEUED,
                'user_id' => $request->user()->id,
            ]);
        });

        if (! $run) {
            return response()->json(['message' => 'Qidiruv allaqachon ishlayapti — tugashini kuting.'], 409);
        }

        RunOperationJob::dispatch($run);

        return response()->json(['message' => 'Qidiruv boshlandi.', 'run' => $this->present($run)], 202);
    }

    /** Sahifa qidiruv tugaguncha har necha soniyada chaqiradi */
    public function status(): JsonResponse
    {
        return response()->json(['run' => $this->latestRun()]);
    }

    private function latestRun(): ?array
    {
        $run = JobRun::with('user:id,name')->where('operation', self::OPERATION)->latest('id')->first();

        return $run ? $this->present($run) : null;
    }

    private function present(JobRun $run): array
    {
        $isActive = in_array($run->status, [JobRun::QUEUED, JobRun::RUNNING], true);
        $stale = $isActive && $run->updated_at?->lt(now()->subMinutes(JobRun::STALE_AFTER_MINUTES));

        return [
            'id' => $run->id,
            'status' => $stale ? 'stale' : $run->status,
            'active' => $isActive && ! $stale,
            'user' => $run->user?->name,
            'created_at' => $run->created_at?->format('Y-m-d H:i'),
            'finished_at' => $run->finished_at?->format('Y-m-d H:i'),
            'queued_seconds' => $run->status === JobRun::QUEUED ? (int) $run->created_at->diffInSeconds(now(), true) : null,
            // buyruq chiqishidan qisqa xulosa: "20 ta tadbir topildi.", "2 ta yangi e'lon." ...
            'summary' => $isActive ? null : $this->summary($run->outputTail(5000)),
        ];
    }

    private function summary(string $output): ?string
    {
        $lines = array_filter(array_map('trim', explode("\n", $output)), fn ($line) => $line !== ''
            && ! str_starts_with($line, '▶') && ! str_starts_with($line, '✔') && ! str_starts_with($line, '✖')
            && ! str_starts_with($line, '[e') && ! str_starts_with($line, '…'));

        return $lines ? implode(' ', $lines) : null;
    }
}
