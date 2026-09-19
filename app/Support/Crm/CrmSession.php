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
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

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
            $process = self::startChromedriver($config['chromedriver_binary']);
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
        $this->attachFile($dialog, $filePath);

        $saveButton = $dialog->findElement(WebDriverBy::xpath(".//button[normalize-space()='Сохранить']"));
        $saveButton->click();

        $this->waitForSaveResult($driver, $timeout);
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

    private function waitForSaveResult(RemoteWebDriver $driver, int $timeout): void
    {
        $wait = new WebDriverWait($driver, $timeout);

        try {
            $wait->until(function (RemoteWebDriver $d) {
                try {
                    $d->findElement(WebDriverBy::cssSelector('dialog[open]'));

                    return false; // oyna hali ochiq — kutishda davom etamiz
                } catch (NoSuchElementException $e) {
                    return true; // oyna yopildi — muvaffaqiyat belgisi
                }
            });
        } catch (Throwable $e) {
            $message = "Import oynasi kutilgan vaqtda yopilmadi (xatolik xabari bo'lishi mumkin).";

            try {
                $dialog = $driver->findElement(WebDriverBy::cssSelector('dialog[open]'));
                $message .= ' Oynadagi matn: ' . trim($dialog->getText());
            } catch (Throwable $inner) {
                //
            }

            throw new RuntimeException($message);
        }
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
