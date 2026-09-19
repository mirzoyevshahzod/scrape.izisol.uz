<?php

namespace App\Support\Crm;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Zanjeer CRM bilan headless Chrome orqali ishlash uchun umumiy yordamchi.
 *
 * `crm:login` va `crm:import-custom-operations` (shu jumladan bir nechta
 * faylni bitta sessiyada yuklaydigan `crm:daily-import`) buyruqlari shu
 * klassdan foydalanadi — chromedriver'ni ishga tushirish, CRM'ga login
 * qilish va "Импортировать" oynasi orqali fayl yuklash kodi bir joyda.
 *
 * MUHIM: bu klass chromedriver va Chrome/Chromium ARTISAN BUYRUG'I ishlayotgan
 * SERVERNING O'ZIDA ishlashini nazarda tutadi — fayl yuklashda beriladigan
 * $filePath shu serverdagi yo'l bo'lishi kerak, chunki brauzer xuddi shu
 * serverda ishlaydi va faylni to'g'ridan-to'g'ri diskdan o'qiydi.
 */
class CrmSession
{
    public RemoteWebDriver $driver;

    private ?Process $chromedriverProcess;

    private function __construct(RemoteWebDriver $driver, ?Process $chromedriverProcess)
    {
        $this->driver = $driver;
        $this->chromedriverProcess = $chromedriverProcess;
    }

    public static function start(array $config, bool $fresh = false): self
    {
        $url = $config['chromedriver_url'];
        $process = null;

        if ($fresh || ! self::isChromedriverRunning($url)) {
            $driverBinary = self::ensureMatchingChromedriver($config);
            $process = self::startChromedriver($driverBinary);
        }

        $options = new ChromeOptions();
        $options->addArguments([
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1280,900',
        ]);

        if (! empty($config['chrome_binary'])) {
            $options->setBinary($config['chrome_binary']);
        }

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $driver = RemoteWebDriver::create($url, $capabilities, 15000, 15000);

        return new self($driver, $process);
    }

    /**
     * Login sahifasiga kirib, email/parol bilan tizimga kiradi.
     * Muvaffaqiyatsiz bo'lsa RuntimeException tashlaydi.
     */
    public function login(array $config, string $email, string $password, int $timeout = 30): void
    {
        $driver = $this->driver;

        $driver->get($config['login_url']);

        $wait = new WebDriverWait($driver, $timeout);

        $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::cssSelector('input[name="email"]')
        ));

        $driver->findElement(WebDriverBy::cssSelector('input[name="email"]'))
            ->clear()
            ->sendKeys($email);

        $driver->findElement(WebDriverBy::cssSelector('input[name="password"]'))
            ->clear()
            ->sendKeys($password);

        $driver->findElement(WebDriverBy::cssSelector('form button[type="submit"]'))->click();

        try {
            $wait->until(function (RemoteWebDriver $d) {
                return ! str_contains($d->getCurrentURL(), '/login');
            });
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Login muvaffaqiyatsiz bo'lgan bo'lishi mumkin: sahifa hali ham /login manzilida qolmoqda (URL: {$driver->getCurrentURL()})."
            );
        }
    }

    /**
     * "Границы -> Торнадо" (custom-operations) sahifasini ochib,
     * berilgan Excel faylni berilgan "Граница" nomi bilan import qiladi.
     *
     * Bir sessiyada (bitta login) bir nechta faylni ketma-ket yuklash uchun
     * shu metodni bir nechta marta chaqirish mumkin — har chaqiruvda sahifa
     * qaytadan ochilib, oyna qaytadan boshidan boshlanadi.
     *
     * Muvaffaqiyatsiz bo'lsa RuntimeException tashlaydi.
     */
    public function importFile(array $config, string $borderName, string $filePath, int $timeout = 60): void
    {
        $driver = $this->driver;

        $driver->get($config['custom_operations_url']);

        $wait = new WebDriverWait($driver, $timeout);

        $importButton = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
            WebDriverBy::xpath("//button[normalize-space()='Импортировать']")
        ));
        $importButton->click();

        $dialog = $wait->until(function (RemoteWebDriver $d) {
            try {
                $el = $d->findElement(WebDriverBy::cssSelector('dialog[open]'));

                return $el->isDisplayed() ? $el : null;
            } catch (Throwable $e) {
                return null;
            }
        });

        $this->selectBorder($dialog, $driver, $borderName, $timeout);

        // Granitsa tanlangandan keyin Livewire oynani qayta render qilishi
        // mumkin (eski $dialog/$fileInput handle'lari "stale" bo'lib qoladi),
        // shuning uchun fayl biriktirishdan oldin oynani qayta topamiz.
        $dialog = $this->freshDialog($driver);
        $this->attachFile($dialog, $filePath);

        $this->saveWithRetry($driver, $timeout);
    }

    private function freshDialog(RemoteWebDriver $driver): WebDriverElement
    {
        return $driver->findElement(WebDriverBy::cssSelector('dialog[open]'));
    }

    public function quit(): void
    {
        try {
            $this->driver->quit();
        } catch (Throwable $e) {
            // brauzerni yopishda xato bo'lsa ham, chromedriver jarayonini
            // to'xtatishga harakat davom etadi
        }

        $this->chromedriverProcess?->stop(3);
    }

    private function selectBorder(WebDriverElement $dialog, RemoteWebDriver $driver, string $borderName, int $timeout): void
    {
        $trigger = $dialog->findElement(WebDriverBy::cssSelector('button[data-flux-select-button]'));
        $trigger->click();

        $search = $dialog->findElement(WebDriverBy::cssSelector('input[placeholder="Search..."]'));
        $search->clear();
        $search->sendKeys($borderName);

        $wait = new WebDriverWait($driver, $timeout);

        $option = $wait->until(function () use ($dialog, $borderName) {
            $options = $dialog->findElements(WebDriverBy::cssSelector('[role="option"]'));

            foreach ($options as $option) {
                if (mb_strtolower(trim($option->getText())) === mb_strtolower(trim($borderName))) {
                    return $option;
                }
            }

            return null;
        });

        if (! $option) {
            $visible = array_map(
                fn (WebDriverElement $o) => $o->getText(),
                $dialog->findElements(WebDriverBy::cssSelector('[role="option"]'))
            );

            throw new RuntimeException(
                "'{$borderName}' nomli granitsa ro'yxatda topilmadi. Ko'rinayotgan variantlar: " . implode(', ', $visible)
            );
        }

        $option->click();
    }

    private function attachFile(WebDriverElement $dialog, string $filePath): void
    {
        $fileInput = $dialog->findElement(WebDriverBy::cssSelector('input[type="file"]'));

        // Fayl input odatda CSS bilan yashiringan bo'ladi ("Choose file"
        // tugmasi shu yashirin inputni bosadi). WebDriver sendKeys() buni
        // ko'pincha muammosiz bajaradi, lekin "element not interactable"
        // xatosini oldini olish uchun uni vaqtincha ko'rinadigan qilamiz —
        // bu faqat headless sessiya ichida, ekranda hech kimga ko'rinmaydi.
        $this->driver->executeScript(
            "arguments[0].style.display='block';" .
            "arguments[0].style.opacity='1';" .
            "arguments[0].style.visibility='visible';" .
            "arguments[0].removeAttribute('hidden');",
            [$fileInput]
        );

        // MUHIM: $filePath shu buyruq ishlayotgan SERVERDAGI yo'l bo'lishi
        // kerak, chunki chromedriver/Chrome xuddi shu serverda ishlaydi va
        // faylni to'g'ridan-to'g'ri diskdan o'qiydi (uzoq Selenium Grid emas).
        $fileInput->sendKeys($filePath);
    }

    /**
     * "Сохранить"ni bosadi va natijani kutadi. Livewire fayl yuklashni
     * (wire:model.defer) asinxron — orqa fonda AJAX orqali — amalga
     * oshiradi, shuning uchun fayl biriktirilgach darhol saqlashga urinilsa,
     * ba'zida "Поле file обязательно для заполнения" xatosi chiqadi (fayl
     * hali yuklanib ulgurmagan). Shu xato chiqqanda, umumiy $timeout
     * doirasida bir necha marta qayta "Сохранить"ni bosib ko'radi.
     * Boshqa turdagi xato chiqsa — darhol RuntimeException tashlaydi.
     */
    private function saveWithRetry(RemoteWebDriver $driver, int $timeout): void
    {
        $deadline = microtime(true) + $timeout;

        while (true) {
            $dialog = $this->freshDialog($driver);
            $dialog->findElement(WebDriverBy::xpath(".//button[normalize-space()='Сохранить']"))->click();

            $remaining = max(1, (int) ceil($deadline - microtime(true)));
            $perAttemptWait = new WebDriverWait($driver, min(10, $remaining));

            try {
                $perAttemptWait->until(function (RemoteWebDriver $d) {
                    try {
                        $d->findElement(WebDriverBy::cssSelector('dialog[open]'));

                        return false; // oyna hali ochiq
                    } catch (NoSuchElementException $e) {
                        return true; // oyna yopildi — muvaffaqiyat
                    }
                });

                return;
            } catch (Throwable $e) {
                // oyna hali ochiq — nega ekanini tekshiramiz
            }

            $dialog = $this->freshDialog($driver);
            $text = trim($dialog->getText());

            $isFileUploadPending = str_contains($text, 'обязательно для заполнения')
                && stripos($text, 'file') !== false;

            if (! $isFileUploadPending) {
                throw new RuntimeException("Saqlash muvaffaqiyatsiz. Oynadagi matn: {$text}");
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException(
                    "Fayl yuklanishi kutilgan vaqtda tugamadi (ehtimol internet/server sekin). Oxirgi xabar: {$text}"
                );
            }

            usleep(700_000);
        }
    }

    /**
     * Chrome va chromedriver versiyalarini solishtiradi. Mos kelmasa
     * (masalan Chrome fonda avtomatik yangilanib ketgan bo'lsa), Chrome for
     * Testing manzilidan Chrome versiyasiga mos chromedriver'ni avtomatik
     * yuklab, joriy chromedriver_binary faylini shu bilan almashtiradi —
     * shu tufayli buyruq har safar qo'lda tuzatishga muhtoj bo'lmaydi.
     *
     * Tarmoqqa faqat versiyalar mos kelmaganda murojaat qilinadi; mos
     * bo'lsa hech qanday tarmoq so'rovi yubormaydi (faqat --version orqali
     * tekshiradi). Tuzatib bo'lmasa (masalan internet yo'q), xatoni faqat
     * log'ga yozib, mavjud faylni o'zgartirmasdan qaytaradi — shunda odatdagi
     * "session not created" xatosi baribir chiqadi, lekin butun buyruq
     * kutilmagan tarzda qulab tushmaydi.
     */
    private static function ensureMatchingChromedriver(array $config): string
    {
        $driverBinary = $config['chromedriver_binary'];
        $chromeBinary = $config['chrome_binary'] ?: 'google-chrome';

        try {
            $chromeVersion = self::detectVersion($chromeBinary);

            if (! $chromeVersion) {
                return $driverBinary;
            }

            $driverVersion = file_exists($driverBinary) ? self::detectVersion($driverBinary) : null;

            if ($driverVersion && self::majorVersion($driverVersion) === self::majorVersion($chromeVersion)) {
                return $driverBinary;
            }

            Log::info('Chromedriver versiyasi Chrome bilan mos kelmadi, avtomatik yangilanmoqda', [
                'chrome_version' => $chromeVersion,
                'driver_version' => $driverVersion,
            ]);

            return self::downloadMatchingChromedriver($chromeVersion, $driverBinary);
        } catch (Throwable $e) {
            Log::warning('Chromedriver avtomatik yangilanmadi: ' . $e->getMessage());

            return $driverBinary;
        }
    }

    private static function detectVersion(string $binary): ?string
    {
        $process = new Process([$binary, '--version']);
        $process->run();

        $output = $process->getOutput() . $process->getErrorOutput();

        return preg_match('/(\d+\.\d+\.\d+\.\d+)/', $output, $m) ? $m[1] : null;
    }

    private static function majorVersion(string $version): string
    {
        return explode('.', $version)[0];
    }

    private static function platformKey(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'win64',
            'Darwin' => str_contains(php_uname('m'), 'arm') ? 'mac-arm64' : 'mac-x64',
            default => 'linux64',
        };
    }

    private static function downloadMatchingChromedriver(string $chromeVersion, string $destPath): string
    {
        $response = Http::timeout(30)->get(
            'https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json'
        );

        if (! $response->successful()) {
            throw new RuntimeException('Chrome for Testing ro\'yxatini olib bo\'lmadi.');
        }

        $versions = $response->json('versions', []);
        $platform = self::platformKey();

        $entry = collect($versions)->firstWhere('version', $chromeVersion);

        if (! $entry) {
            // Aynan shu versiya "known-good" ro'yxatida hali bo'lmasligi
            // mumkin (juda yangi chiqarilgan bo'lsa) — bir xil major
            // versiyadagi eng oxirgisini olamiz.
            $major = self::majorVersion($chromeVersion);

            $entry = collect($versions)
                ->filter(fn ($v) => self::majorVersion($v['version']) === $major)
                ->last();
        }

        if (! $entry) {
            throw new RuntimeException("Chrome {$chromeVersion} uchun mos chromedriver topilmadi.");
        }

        $downloadUrl = collect($entry['downloads']['chromedriver'] ?? [])
            ->firstWhere('platform', $platform)['url'] ?? null;

        if (! $downloadUrl) {
            throw new RuntimeException("'{$platform}' uchun chromedriver havolasi topilmadi.");
        }

        $zipResponse = Http::timeout(60)->get($downloadUrl);

        if (! $zipResponse->successful()) {
            throw new RuntimeException("Chromedriver zip yuklab bo'lmadi: {$downloadUrl}");
        }

        $tmpDir = sys_get_temp_dir() . '/crm-chromedriver-' . uniqid();
        mkdir($tmpDir, 0777, true);
        $zipPath = $tmpDir . '/chromedriver.zip';
        file_put_contents($zipPath, $zipResponse->body());

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Chromedriver zip ochilmadi.');
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        $binaryName = PHP_OS_FAMILY === 'Windows' ? 'chromedriver.exe' : 'chromedriver';
        $extracted = glob($tmpDir . '/*/' . $binaryName);

        if (empty($extracted)) {
            throw new RuntimeException('Yuklangan zip ichida chromedriver topilmadi.');
        }

        $destDir = dirname($destPath);

        if (! is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        copy($extracted[0], $destPath);

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($destPath, 0755);
        }

        self::deleteDirectory($tmpDir);

        Log::info("Chromedriver {$destPath} ga yangilandi.");

        return $destPath;
    }

    private static function deleteDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? self::deleteDirectory($item) : @unlink($item);
        }

        @rmdir($dir);
    }

    private static function isChromedriverRunning(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '127.0.0.1';
        $port = parse_url($url, PHP_URL_PORT) ?: 9515;

        $handle = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($handle) {
            fclose($handle);

            return true;
        }

        return false;
    }

    private static function startChromedriver(string $binary): Process
    {
        $port = 9515;

        $process = new Process([$binary, "--port={$port}"]);
        // chromedriver har bir so'rovni log qilib turadi; buferni o'qib
        // turmasak, OS pipe to'lib, chromedriver yozishda "qotib qoladi"
        // va butun buyruq abadiy kutib qoladi. Shu uchun outputni o'chiramiz.
        $process->disableOutput();
        $process->start();

        $attempts = 0;
        while (! self::isChromedriverRunning("http://127.0.0.1:{$port}") && $attempts < 20) {
            usleep(250_000);
            $attempts++;
        }

        return $process;
    }
}
