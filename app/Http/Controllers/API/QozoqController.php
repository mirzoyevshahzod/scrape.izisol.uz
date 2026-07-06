<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Jobs\QozoqScrapeJob;

class QozoqController extends Controller
{
    public function scrape($checkpoint)
    {
        if (!$checkpoint) {

            return response()->json([
                'status' => false,
                'message' => 'Checkpoint tanlanmagan'
            ], 400);
        }

        QozoqScrapeJob::dispatch($checkpoint);

        return response()->json([
            'status' => true,
            'message' => 'Scraping queuega yuborildi'
        ]);
    }
     public function index()
    {
        return view('qozoq', [
            'checkpoints' => $this->getCheckpoints()
        ]);
    }

    private function getCheckpointName($checkpoint)
    {
        $map = [
            '238304120665000000' => 'Акбалшык - Воскресенское',
            '238245356822000000' => 'Аксай – Илек',
            '238238650340000000' => 'Алимбет – Орск',
            '238309634433000000' => 'Амангельды – Невольное',
            '238304557899000000' => 'Аят - Николаевка',
            '238316180166000000' => 'Бидаик – Одесское',
            '238239148521000000' => 'Жайсан – Сагарчин',
            '238316459162000000' => 'Жана Жол – Петухово',
            '238246015768000000' => 'Жаныбек – Вишневка',
            '238307181634000000' => 'Жезкент – Горняк',
            '238304850217000000' => 'Желкуар – Мариинский',
            '238305215351000000' => 'Кайрак – Бугристое',
            '238305435419000000' => 'Кондыбай – Комсомольский',
            '238316732771000000' => 'Каракога – Исилькуль',
            '238239515479000000' => 'Карашатау - Светлый',
            '238311401124000000' => 'Косак – Павловка',
            '238308566679000000' => 'Коянбай – Малиновое Озеро',
            '238243101359000000' => 'Курмангазы – Караузек',
            '238316951727000000' => 'Кызыл Жар – Казанское',
            '238315911519000000' => 'Найза – Павловка (Славгород)',
            '238246712805000000' => 'Орда – Полынный',
            '238247045484000000' => 'Сырым – Маштаково',
            '238303161413000000' => 'Таскала – Озинки',
            '238305660457000000' => 'Убаган – Звериноголовское',
            '238244498776000000' => 'Убе – Михайловка',
            '238315711818000000' => 'Урлютобе – Ольховка',
            '238303383491000000' => 'Шаган – Теплое',
            '238309028892000000' => 'Шарбакты – Кулунда',
            '238306291664000000' => 'Ауыл – Веселоярск',
            '234422898551000000' => 'Атамекен - Гулистан',
            '231576795648000000' => 'Б. Конысбаева - Яллама',
            '234531528148000000' => 'Казыгурт - Майский',
            '238236183776000000' => 'Капланбек - Навои',
            '224752846845000000' => 'Тажен - Каракалпакстан',
            '224752450232000000' => 'Темир-Баба - Карабугаз',
            '224749863825000000' => 'Бахты - Покиту',
            '215778822067000000' => 'Достык - Алашанькоу',
            '222979531669000000' => 'Калжат - Дулаты',
            '224751327844000000' => 'Майкапчагай - Зимунай',
            '222978891854000000' => 'Нур Жолы - Хоргос',
            '291817455346000000' => 'Айша-Биби - Чон-Какпа',
            '291818150184000000' => 'Аухатты - Кенбулын',
            '291820603631000000' => 'Кеген - Каркыра',
            '291819404994000000' => 'Кордай - Ак-Жол',
            '291821145135000000' => 'Сартобе - Токмок',
            '291818866418000000' => 'Сыпатай Батыр - Чалдыбар',
            '314889163298000000' => 'Порт Курык'
        ];

        return $map[$checkpoint] ?? $checkpoint;
    }


     private function getCheckpoints()
    {
        return  [
            'Kazakhstan - Russia' => '>>>>>>>>>>>>>>>>>>>>>>>> Kazakhstan - Russia <<<<<<<<<<<<<<<<<<<<<<<<<',
            '238304120665000000' => 'Акбалшык - Воскресенское',
            '238245356822000000' => 'Аксай – Илек',
            '238238650340000000' => 'Алимбет – Орск',
            '238309634433000000' => 'Амангельды – Невольное',
            '238306291664000000' => 'Ауыл – Веселоярск',
            '238304557899000000' => 'Аят - Николаевка',
            '238316180166000000' => 'Бидаик – Одесское',
            '238239148521000000' => 'Жайсан – Сагарчин',
            '238316459162000000' => 'Жана Жол – Петухово',
            '238246015768000000' => 'Жаныбек – Вишневка',
            '238307181634000000' => 'Жезкент – Горняк',
            '238304850217000000' => 'Желкуар – Мариинский',
            '238316732771000000' => 'Каракога – Исилькуль',
            '238239515479000000' => 'Карашатау - Светлый',
            '238311401124000000' => 'Косак – Павловка',
            '238308566679000000' => 'Коянбай – Малиновое Озеро',
            '238243101359000000' => 'Курмангазы – Караузек',
            '238316951727000000' => 'Кызыл Жар – Казанское',
            '238315911519000000' => 'Найза – Павловка (Славгород)',
            '238246712805000000' => 'Орда – Полынный',
            '238247045484000000' => 'Сырым – Маштаково',
            '238303161413000000' => 'Таскала – Озинки',
            '238305660457000000' => 'Убаган – Звериноголовское',
            '238244498776000000' => 'Убе – Михайловка',
            '238315711818000000' => 'Урлютобе – Ольховка',
            '238303383491000000' => 'Шаган – Теплое',
            '238309028892000000' => 'Шарбакты – Кулунда',
            'Kazakhstan - Uzbekistan' => '>>>>>>>>>>>>>>>>>>>>>>>> Kazakhstan - Uzbekistan <<<<<<<<<<<<<<<<<<<<<<<<<',
            '234422898551000000' => 'Атамекен - Гулистан',
            '231576795648000000' => 'Б. Конысбаева - Яллама',
            '234531528148000000' => 'Казыгурт - Майский',
            '238236183776000000' => 'Капланбек - Навои',
            '224752846845000000' => 'Тажен - Каракалпакстан',
            'Kazakhstan - Turkmenistan' => '>>>>>>>>>>>>>>>>>>>>>>>> Kazakhstan - Turkmenistan <<<<<<<<<<<<<<<<<<<<<<<<<',
            '224752450232000000' => 'Темир-Баба - Карабугаз',
            'Kazakhstan - China' => '>>>>>>>>>>>>>>>>>>>>>>>> Kazakhstan - China <<<<<<<<<<<<<<<<<<<<<<<<<',
            '224749863825000000' => 'Бахты - Покиту',
            '215778822067000000' => 'Достык - Алашанькоу',
            '222979531669000000' => 'Калжат - Дулаты',
            '224751327844000000' => 'Майкапчагай - Зимунай',
            '222978891854000000' => 'Нур Жолы - Хоргос',
            'Kazakhstan - Kyrgyzstan' => '>>>>>>>>>>>>>>>>>>>>>>>> Kazakhstan - Kyrgyzstan <<<<<<<<<<<<<<<<<<<<<<<<<',
            '291817455346000000' => 'Айша-Биби - Чон-Какпа',
            '291818150184000000' => 'Аухатты - Кенбулын',
            '291820603631000000' => 'Кеген - Каркыра',
            '291819404994000000' => 'Кордай - Ак-Жол',
            '291821145135000000' => 'Сартобе - Токмок',
            '291818866418000000' => 'Сыпатай Батыр - Чалдыбар',
            'Kazakhstan' => '>>>>>>>>>>>>>>>>>>>>>>>> Kazakhstan <<<<<<<<<<<<<<<<<<<<<<<<<',
            '314889163298000000' => 'Порт Курык'
        ];

    }

    public function getFiles()
    {
        $files = glob(storage_path('app/qozoq/*.xlsx'));

         usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });


        return response()->json([
            'status' => true,
            'files'  => array_map('basename', $files)
        ]);
    }

    public function download($file)
    {
        $file = basename($file); // 🔒 XAVFSIZLIK
        $path = storage_path('app/qozoq/' . $file);

        if (!file_exists($path)) {
            return response()->json([
                'status'  => false,
                'message' => 'Fayl topilmadi'
            ], 404);
        }

        return response()->download($path);
    }


    public function upload(Request $request)
    {
        $request->validate([
            'excel' => [
                'required',
                'file',
                'mimes:xlsx,xls'
            ]
        ]);

        $file = $request->file('excel');

        // Excel ochish
        $spreadsheet = IOFactory::load($file->getPathname());

        $sheet = $spreadsheet->getActiveSheet();

        // 1-qatorni olish
        $headers = $sheet->rangeToArray('A1:F1')[0];

        $expectedHeaders = [
            'Chegara nomi',
            'Mashina raqami',
            'Sana va vaqt',
            'Status',
            'Company',
            'Telefon'
        ];

        // Header tekshirish
        if ($headers !== $expectedHeaders) {

            return response()->json([
                'message' => 'Excel formati noto‘g‘ri'
            ], 422);
        }


        $fileName = $file->getClientOriginalName();

        $pattern = '/^qozoq_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_tekshirilgan\.(xlsx|xls)$/';

        if (!preg_match($pattern, $fileName)) {

            return response()->json([
                'message' => 'File nomi noto‘g‘ri formatda'
            ], 422);
        }

        $path = storage_path('app/import_qozoq');

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $path = storage_path('app/import_qozoq');

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $fullPath = $path . '/' . $fileName;

        if (file_exists($fullPath)) {

            return response()->json([
                'message' => 'Bu file allaqachon mavjud'
            ], 422);
        }


        $file->move($path, $fileName);

        return response()->json([
            'message' => 'Excel muvaffaqiyatli yuklandi',
            'file_name' => $fileName
        ]);
    }

    public function getFilesImport()
    {
        $files = glob(storage_path('app/import_qozoq/*.xlsx'));

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return response()->json([
            'status' => true,
            'files'  => array_map('basename', $files)
        ]);
    }

    public function downloadImportFile($file)
    {
        $file = basename($file); // 🔒 XAVFSIZLIK
        $path = storage_path('app/import_qozoq/' . $file);

        if (!file_exists($path)) {
            return response()->json([
                'status'  => false,
                'message' => 'Fayl topilmadi'
            ], 404);
        }

        return response()->download($path);
    }

}
