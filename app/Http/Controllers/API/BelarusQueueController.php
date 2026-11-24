<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DeclarantQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Serverdagi brauzer kengaytmasi (deploy/belarus-queue-browser) captcha
 * yechilgan mon.declarant.by sahifasi olgan navbat javobini shu yerga
 * yuboradi. Javob keshga "kutilmoqda" bo'lib yoziladi — Excel va CRM
 * import'ni belarus:queue-sync (root cron) bajaradi, shunda fayllar
 * boshqa scrape fayllari bilan bir xil egada yoziladi.
 */
class BelarusQueueController extends Controller
{
    public const PENDING_KEY = 'belarus-queue:pending:';

    public function ingest(Request $request): JsonResponse
    {
        $token = (string) config('belarus_queue.ingest_token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('X-Ingest-Token'))) {
            return response()->json(['ok' => false, 'error' => 'token'], 403);
        }

        $data = $request->json()->all();
        $zone = DeclarantQueue::zoneByCheckpoint(data_get($data, 'info.id'));

        if ($zone === null) {
            return response()->json(['ok' => false, 'error' => 'unknown checkpoint'], 422);
        }

        if (! DeclarantQueue::hasData($data)) {
            // captcha yechilmagan sahifa ham shunday javob oladi — eski yaxshi ma'lumot qolsin
            return response()->json(['ok' => false, 'zone' => $zone, 'error' => 'empty queue'], 422);
        }

        Cache::put(self::PENDING_KEY . $zone, ['received_at' => now()->getTimestamp(), 'data' => $data], now()->addHours(2));

        return response()->json(['ok' => true, 'zone' => $zone, 'trucks' => count($data['truckLiveQueue'] ?? [])]);
    }
}
