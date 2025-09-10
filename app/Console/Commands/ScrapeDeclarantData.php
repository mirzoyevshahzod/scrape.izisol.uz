<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Interactions\WebDriverActions as InteractionsWebDriverActions;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ScrapeDeclarantData extends Command
{
    protected $signature = 'scrape:declarant {region} {--url=} {--name=}';
    protected $description = 'Universal scraper for all Declarant zones - extracts Uzbek vehicles data';

    private $driver;
    private $carNumbers = []; // Topilgan mashina raqamlari
    private $allData = []; // Barcha ma'lumotlar
    
    // O'zbek mashina raqamlari pattern-lari
    private $uzbekPatterns = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
    ];

    public function handle()
    {
        set_time_limit(0);
        Log::info('IntegratedScrape: Jarayon boshlandi');

        try {
            $region = $this->argument('region');
            $url = $this->option('url');
            $name = $this->option('name');

            // 1-bosqich: Declarant saytidan mashina raqamlarini yig'ish
            $this->info("1-bosqich: Declarant saytidan ma'lumot yig'ish...");
            $this->scrapeDeclarantData($region, $url, $name);

            if (empty($this->carNumbers)) {
                $this->warn('Declarant saytidan mashina raqamlari topilmadi!');
                return 1;
            }

            $this->info("Jami " . count($this->carNumbers) . " ta o'zbek mashina raqami topildi");

            // 2-bosqich: Har bir mashina raqami uchun Mintrans ma'lumotlarini olish
            $this->info("2-bosqich: Mintrans saytidan qo'shimcha ma'lumotlar olish...");
            $this->scrapeMintransData();

            // 3-bosqich: Ma'lumotlarni Excel va Database ga saqlash
            $this->info("3-bosqich: Ma'lumotlarni saqlash...");
            $this->saveAllData($region, $name);

            $this->info('Jarayon muvaffaqiyatli yakunlandi!');
            return 0;

        } catch (Exception $e) {
            Log::error('IntegratedScrape xato: ' . $e->getMessage());
            $this->error('Xato: ' . $e->getMessage());
            $this->closeWebDriver();
            return 1;
        }
    }

    /**
     * Declarant saytidan mashina raqamlarini yig'ish
     */
    private function scrapeDeclarantData($region, $url, $name)
    {
        $this->initializeWebDriver();
        
        try {
            $this->driver->get($url);
            sleep(8);

            $maxPages = 15;
            $currentPage = 1;
            $consecutiveEmptyPages = 0;

            while ($currentPage <= $maxPages && $consecutiveEmptyPages < 3) {
                $this->info("Declarant sahifa {$currentPage} ni qayta ishlash...");

                try {
                    // Sahifa yuklanishini kutish
                    $wait = new WebDriverWait($this->driver, 20);
                    $wait->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::cssSelector('table.cdk-table tbody tr')
                        )
                    );

                    $rows = $this->driver->findElements(WebDriverBy::cssSelector('table.cdk-table tbody tr.cdk-row'));
                    
                    if (empty($rows)) {
                        $consecutiveEmptyPages++;
                        $this->warn("Sahifa {$currentPage} da ma'lumot topilmadi");
                        
                        if (!$this->goToNextPage($currentPage)) break;
                        $currentPage++;
                        continue;
                    }

                    $consecutiveEmptyPages = 0;

                    foreach ($rows as $row) {
                        try {
                            $cells = $row->findElements(WebDriverBy::cssSelector('td.cdk-cell'));
                            
                            if (count($cells) < 6) continue;

                            $orderNumber = trim($cells[0]->getText());
                            $queueType = trim($cells[1]->getText());
                            $regNumber = trim($cells[2]->getText());
                            $registrationDate = trim($cells[3]->getText());
                            $statusChanged = trim($cells[4]->getText());
                            $status = trim($cells[5]->getText());

                            // O'zbek mashina raqamini tekshirish
                            if ($this->isUzbekVehicle($regNumber)) {
                                $this->carNumbers[] = $regNumber;
                                
                                // Declarant ma'lumotlarini saqlash
                                $this->allData[$regNumber] = [
                                    'declarant' => [
                                        'order_number' => $orderNumber,
                                        'queue_type' => $queueType,
                                        'reg_number' => $regNumber,
                                        'registration_date' => $registrationDate,
                                        'status_changed' => $statusChanged,
                                        'status' => preg_replace('/^\s*\w*\s*/', '', $status),
                                        'region' => $region
                                    ],
                                    'mintrans' => [] // Keyinchalik to'ldiriladi
                                ];

                                Log::info("O'zbek mashina topildi: {$regNumber}");
                            }

                        } catch (Exception $e) {
                            Log::warning("Qatorni o'qishda xato: " . $e->getMessage());
                        }
                    }

                    if (!$this->goToNextPage($currentPage)) break;
                    $currentPage++;

                } catch (Exception $e) {
                    Log::warning("Sahifa {$currentPage} da xato: " . $e->getMessage());
                    $consecutiveEmptyPages++;
                    
                    if (!$this->goToNextPage($currentPage)) break;
                    $currentPage++;
                }
            }

        } catch (Exception $e) {
            Log::error("Declarant scraping xato: " . $e->getMessage());
        }

        $this->closeWebDriver();
    }

    /**
     * Mintrans saytidan qo'shimcha ma'lumotlar olish
     */
    private function scrapeMintransData()
    {
        $this->initializeWebDriver();
        
        try {
            $this->driver->get('https://info.mintrans.uz/#/info/onSearch');
            $wait = new WebDriverWait($this->driver, 10);
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::tagName('body')
            ));

            $processedCount = 0;
            $totalCars = count($this->carNumbers);

            foreach ($this->carNumbers as $carNumber) {
                $processedCount++;
                $this->info("Mintrans qidiruv ({$processedCount}/{$totalCars}): {$carNumber}");

                try {
                    // Sahifani yangilash
                    $this->driver->navigate()->refresh();
                    $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::tagName('body')
                    ));

                    // Inputga mashina raqamini yozish
                    $input = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
                        WebDriverBy::cssSelector('input[ng-model="object.autoNumber"]')
                    ));
                    $input->clear();
                    $input->sendKeys($carNumber);

                    // Qidiruv tugmasini bosish
                    $searchButton = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
                        WebDriverBy::cssSelector('button[ng-click="object.searchAutoNumber(object.autoNumber)"]')
                    ));
                    $searchButton->click();
                    
                    sleep(3);

                    // Ma'lumotlarni olish
                    $mintransData = $this->extractMintransData($wait);
                    
                    // Ma'lumotlarni asosiy arrayga qo'shish
                    if (isset($this->allData[$carNumber])) {
                        $this->allData[$carNumber]['mintrans'] = $mintransData;
                    }

                    Log::info("Mintrans ma'lumoti olindi: {$carNumber}");

                } catch (Exception $e) {
                    Log::warning("Mintrans qidiruv xato ({$carNumber}): " . $e->getMessage());
                    
                    // Ma'lumot topilmasa bo'sh array
                    if (isset($this->allData[$carNumber])) {
                        $this->allData[$carNumber]['mintrans'] = [];
                    }
                }

                // Har 10 ta mashina uchun kichik tanaffus
                if ($processedCount % 10 === 0) {
                    sleep(2);
                }
            }

        } catch (Exception $e) {
            Log::error("Mintrans scraping xato: " . $e->getMessage());
        }

        $this->closeWebDriver();
    }

    /**
     * Mintrans ma'lumotlarini ajratib olish
     */
    private function extractMintransData($wait)
    {
        try {
            $dataDiv = $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::id('data3')
            ));

            $data = [];

            // h4 dan rusumi va yuk qobiliyatini olish
            try {
                $h4 = $dataDiv->findElement(WebDriverBy::tagName('h4'));
                $h4Text = $h4->getText();
                
                if (preg_match('/Rusumi: (.*?)\s*Yuk ko\'tarish qobiliyati:\s*(.*?)$/', $h4Text, $matches)) {
                    $data['rusumi'] = trim($matches[1]);
                    $data['yuk_qobiliyati'] = trim($matches[2]);
                }
            } catch (Exception $e) {
                Log::warning("h4 ma'lumotini olishda xato: " . $e->getMessage());
            }

            // Jadval ma'lumotlarini olish
            try {
                $table = $dataDiv->findElement(WebDriverBy::cssSelector('table.table-bordered.table-striped.table-striped2'));
                $rows = $table->findElements(WebDriverBy::tagName('tr'));

                if (count($rows) > 1) {
                    $dataRow = $rows[1];
                    $cells = $dataRow->findElements(WebDriverBy::tagName('td'));

                    if (count($cells) >= 11) {
                        $data['license'] = $cells[1]->getText();
                        $data['state_number'] = $cells[2]->getText();
                        $data['company'] = $cells[3]->getText();
                        $data['activity_type'] = $cells[4]->getText();
                        $data['transport_type'] = $cells[5]->getText();
                        $data['cargo_type'] = $cells[6]->getText();
                        $data['issue_date'] = $cells[7]->getText();
                        $data['expiry_date'] = $cells[8]->getText();
                        $data['status'] = $cells[9]->getText();
                        $data['regional_office'] = $cells[10]->getText();

                        // Telefon raqamini olish (tooltip)
                        try {
                            $actions = new InteractionsWebDriverActions($this->driver);
                            $actions->moveToElement($cells[3])->perform();
                            sleep(1);

                            $tooltip = $cells[3]->findElement(WebDriverBy::cssSelector('span.tooltip2 em'));
                            $tooltipText = $tooltip->getText();

                            if (preg_match('/(?:\+?998|\b998)?[- ]?\d{2}[- ]?\d{3}[- ]?\d{2}[- ]?\d{2}\b/', $tooltipText, $matches)) {
                                $data['phone_number'] = $matches[0];
                            }
                        } catch (Exception $e) {
                            $data['phone_number'] = '';
                            Log::warning("Telefon raqamini olishda xato: " . $e->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning("Jadval ma'lumotini olishda xato: " . $e->getMessage());
            }

            return $data;

        } catch (Exception $e) {
            Log::warning("Mintrans ma'lumotini olishda xato: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Barcha ma'lumotlarni saqlash
     */
    private function saveAllData($region, $name)
    {
        // Excel fayl yaratish
        $this->saveToExcel($region, $name);
        
        // Database ga saqlash
        $this->saveToDatabase();
    }

    /**
     * Excel ga saqlash
     */
    private function saveToExcel($region, $name)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Sarlavhalar
        $headers = [
            'A1' => 'Tartib raqami',
            'B1' => 'Navbat turi',
            'C1' => 'Mashina raqami',
            'D1' => 'Ro\'yxatga olingan sana',
            'E1' => 'Holat o\'zgargan',
            'F1' => 'Holati',
            'G1' => 'Hudud',
            'H1' => 'Rusumi',
            'I1' => 'Yuk ko\'tarish qobiliyati',
            'J1' => 'Litsenziya varaqasi',
            'K1' => 'Davlat raqami',
            'L1' => 'Korxona nomi',
            'M1' => 'Telefon raqami',
            'N1' => 'Faoliyat turi',
            'O1' => 'Transport turi',
            'P1' => 'Yuk turi',
            'Q1' => 'Berilgan sana',
            'R1' => 'Amal qilish muddati',
            'S1' => 'Mintrans holati',
            'T1' => 'Hududiy boshqarma'
        ];

        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        $row = 2;
        foreach ($this->allData as $carNumber => $data) {
            $declarant = $data['declarant'];
            $mintrans = $data['mintrans'];

            $sheet->setCellValue('A' . $row, $declarant['order_number'] ?? '');
            $sheet->setCellValue('B' . $row, $declarant['queue_type'] ?? '');
            $sheet->setCellValue('C' . $row, $declarant['reg_number'] ?? '');
            $sheet->setCellValue('D' . $row, $declarant['registration_date'] ?? '');
            $sheet->setCellValue('E' . $row, $declarant['status_changed'] ?? '');
            $sheet->setCellValue('F' . $row, $declarant['status'] ?? '');
            $sheet->setCellValue('G' . $row, $declarant['region'] ?? '');
            
            $sheet->setCellValue('H' . $row, $mintrans['rusumi'] ?? '');
            $sheet->setCellValue('I' . $row, $mintrans['yuk_qobiliyati'] ?? '');
            $sheet->setCellValue('J' . $row, $mintrans['license'] ?? '');
            $sheet->setCellValue('K' . $row, $mintrans['state_number'] ?? '');
            $sheet->setCellValue('L' . $row, $mintrans['company'] ?? '');
            $sheet->setCellValueExplicit('M' . $row, $mintrans['phone_number'] ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue('N' . $row, $mintrans['activity_type'] ?? '');
            $sheet->setCellValue('O' . $row, $mintrans['transport_type'] ?? '');
            $sheet->setCellValue('P' . $row, $mintrans['cargo_type'] ?? '');
            $sheet->setCellValue('Q' . $row, $mintrans['issue_date'] ?? '');
            $sheet->setCellValue('R' . $row, $mintrans['expiry_date'] ?? '');
            $sheet->setCellValue('S' . $row, $mintrans['status'] ?? '');
            $sheet->setCellValue('T' . $row, $mintrans['regional_office'] ?? '');

            $row++;
        }

        // Faylni saqlash
        $fileName = "integrated-data-{$name}-" . date('Y-m-d-H-i-s') . ".xlsx";
        $filePath = storage_path("app/public/{$fileName}");
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $this->info("Excel fayl saqlandi: {$filePath}");
        Log::info("Excel fayl yaratildi: {$filePath}");
    }

    /**
     * Database ga saqlash
     */
    private function saveToDatabase()
    {
        foreach ($this->allData as $carNumber => $data) {
            try {
                DB::table('vehicle_data')->updateOrInsert(
                    ['reg_number' => $carNumber],
                    [
                        // Declarant ma'lumotlari
                        'order_number' => $data['declarant']['order_number'] ?? null,
                        'queue_type' => $data['declarant']['queue_type'] ?? null,
                        'reg_number' => $data['declarant']['reg_number'] ?? null,
                        'registration_date' => $data['declarant']['registration_date'] ?? null,
                        'status_changed' => $data['declarant']['status_changed'] ?? null,
                        'declarant_status' => $data['declarant']['status'] ?? null,
                        'region' => $data['declarant']['region'] ?? null,
                        
                        // Mintrans ma'lumotlari
                        'rusumi' => $data['mintrans']['rusumi'] ?? null,
                        'yuk_qobiliyati' => $data['mintrans']['yuk_qobiliyati'] ?? null,
                        'license' => $data['mintrans']['license'] ?? null,
                        'state_number' => $data['mintrans']['state_number'] ?? null,
                        'company' => $data['mintrans']['company'] ?? null,
                        'phone_number' => $data['mintrans']['phone_number'] ?? null,
                        'activity_type' => $data['mintrans']['activity_type'] ?? null,
                        'transport_type' => $data['mintrans']['transport_type'] ?? null,
                        'cargo_type' => $data['mintrans']['cargo_type'] ?? null,
                        'issue_date' => $data['mintrans']['issue_date'] ?? null,
                        'expiry_date' => $data['mintrans']['expiry_date'] ?? null,
                        'mintrans_status' => $data['mintrans']['status'] ?? null,
                        'regional_office' => $data['mintrans']['regional_office'] ?? null,
                        
                        'updated_at' => now(),
                        'created_at' => now()
                    ]
                );

                Log::info("Database ga saqlandi: {$carNumber}");

            } catch (Exception $e) {
                Log::error("Database saqlashda xato ({$carNumber}): " . $e->getMessage());
            }
        }

        $this->info("Database ga " . count($this->allData) . " ta yozuv saqlandi");
    }

    /**
     * O'zbek mashina raqamini tekshirish
     */
    private function isUzbekVehicle($regNumber)
    {
        $regNumber = strtoupper(trim($regNumber));
        $regNumber = preg_replace('/[^A-Z0-9]/', '', $regNumber);
        
        foreach ($this->uzbekPatterns as $pattern) {
            if (preg_match($pattern, $regNumber)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Keyingi sahifaga o'tish
     */
    private function goToNextPage($currentPage)
    {
        try {
            $paginationSelectors = [
                'button[aria-label="Next page"]',
                '.pagination .next:not(.disabled)',
                '.mat-paginator-next-button:not([disabled])'
            ];

            foreach ($paginationSelectors as $selector) {
                try {
                    $elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
                    
                    foreach ($elements as $element) {
                        if ($element->isDisplayed() && $element->isEnabled()) {
                            $this->driver->executeScript("arguments[0].scrollIntoView(true);", [$element]);
                            sleep(1);
                            $element->click();
                            sleep(4);
                            return true;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            return false;

        } catch (Exception $e) {
            Log::warning("Pagination xato: " . $e->getMessage());
            return false;
        }
    }

    /**
     * WebDriver ni ishga tushirish
     */
    private function initializeWebDriver()
    {
        try {
            $host = 'http://localhost:4444/wd/hub';
            $chromeOptions = new ChromeOptions();
            $chromeOptions->addArguments([
                '--no-sandbox', 
                '--disable-dev-shm-usage', 
                '--disable-gpu',
                '--window-size=1920,1080',
                '--disable-blink-features=AutomationControlled',
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
            
            $this->driver = RemoteWebDriver::create($host, $capabilities);
            $this->driver->manage()->timeouts()->implicitlyWait(5);
            
            Log::info('WebDriver ishga tushdi');
            return true;

        } catch (Exception $e) {
            Log::error('WebDriver xato: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * WebDriver ni yopish
     */
    private function closeWebDriver()
    {
        try {
            if ($this->driver) {
                $this->driver->quit();
                $this->driver = null;
                Log::info('WebDriver yopildi');
            }
        } catch (Exception $e) {
            Log::error('WebDriver yopishda xato: ' . $e->getMessage());
        }
    }
}