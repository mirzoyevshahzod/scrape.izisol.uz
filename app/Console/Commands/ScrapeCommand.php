<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Interactions\WebDriverActions as InteractionsWebDriverActions;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\TurkeyData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ScrapeCommand extends Command
{
    protected $signature = 'scrape';
    protected $description = 'Scrape Uzbek license plates from hopatirparki.com, then from mintrans.uz, and export to Excel and DB with detailed logging (PHP 8.3 compatible)';

    private $driver;
    private $carNumbers = [];
    private $allData = [];
    private $uzbekPatterns = [
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)M\d{6}$/',
    ];
    
    // Fayl yo'lini saqlash uchun property
    private $excelFilePath;

    public function handle()
    {
        set_time_limit(0);
        Log::info('ScrapeCommand: Process started');

        try {
            // 1. Scrape hopatirparki.com
            $this->info('Step 1: Scraping data from hopatirparki.com...');
            $this->scrapeHopatirparkiData();

            if (empty($this->carNumbers)) {
                $this->warn('No Uzbek license plates found on hopatirparki.com!');
                return 1;
            }

            $this->info('Found ' . count($this->carNumbers) . ' Uzbek license plates');

            // 2. Scrape mintrans.uz
            $this->info('Step 2: Fetching additional data from mintrans.uz...');
            $this->scrapeMintransData();

            // 3. Save to Excel and Database
            $this->info('Step 3: Saving data...');
            $this->saveAllData();

            // 4. Excel fayl yo'lini qaytarish (session yoki cache orqali)
            if ($this->excelFilePath && file_exists($this->excelFilePath)) {
                // Fayl yo'lini cache ga saqlash
                cache(['excel_file_path' => $this->excelFilePath], now()->addMinutes(30));
                Log::info('Excel file path cached: ' . $this->excelFilePath);
            }

            $this->info('Scraping process completed successfully!');
            return 0;

        } catch (Exception $e) {
            Log::error('ScrapeCommand error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Error: ' . $e->getMessage());
            $this->closeWebDriver();
            return 1;
        }
    }

    private function scrapeHopatirparkiData()
    {
        $this->initializeWebDriver();

        try {
            $url = 'https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp';
            $this->driver->get($url);
            $wait = new WebDriverWait($this->driver, 20);
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body')));
            $this->info('Hopatirparki website loaded');

            $maxPages = 15;
            $currentPage = 1;
            $consecutiveEmptyPages = 0;

            while ($currentPage <= $maxPages && $consecutiveEmptyPages < 3) {
                $this->info("Processing page {$currentPage}...");

                try {
                    $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('myTable')));
                    $rows = $this->driver->findElements(WebDriverBy::cssSelector('#myTable tbody tr'));
                    $this->info("Found " . count($rows) . " rows on page {$currentPage}");

                    if (empty($rows)) {
                        $consecutiveEmptyPages++;
                        $this->warn("No data found on page {$currentPage}");
                        if (!$this->goToNextPage($currentPage)) break;
                        $currentPage++;
                        continue;
                    }

                    $consecutiveEmptyPages = 0;

                    foreach ($rows as $rowIdx => $row) {
                        try {
                            $cells = $row->findElements(WebDriverBy::tagName('td'));
                            if (count($cells) < 5) {
                                $this->warn("Insufficient columns in row " . ($rowIdx + 1) . ", skipping.");
                                continue;
                            }

                            $sira = trim($cells[0]->getText());
                            $giris = trim($cells[1]->getText());
                            $plakaFull = trim($cells[2]->getText());
                            $tarih = trim($cells[3]->getText());
                            $yer = trim($cells[4]->getText());

                            $plaka = trim(preg_replace('/\s*\(.*\)/', '', explode("\n", $plakaFull)[0]));

                            if ($this->isUzbekVehicle($plaka)) {
                                $this->carNumbers[] = $plaka;
                                $this->allData[$plaka] = [
                                    'hopatirparki' => [
                                        'sira' => $sira,
                                        'giris' => $giris,
                                        'plaka' => $plaka,
                                        'tarih' => $tarih,
                                        'yer' => $yer
                                    ],
                                    'mintrans' => []
                                ];
                                $this->info("Uzbek plate found: {$plaka}");
                                Log::info("Uzbek plate added: {$plaka}");
                            }

                        } catch (Exception $e) {
                            $this->warn("Error processing row " . ($rowIdx + 1) . ": {$e->getMessage()}");
                            Log::warning("Row processing error: {$e->getMessage()}");
                        }
                    }

                    if (!$this->goToNextPage($currentPage)) break;
                    $currentPage++;

                } catch (Exception $e) {
                    $this->warn("Page {$currentPage} error: {$e->getMessage()}");
                    Log::warning("Page {$currentPage} error: {$e->getMessage()}");
                    $consecutiveEmptyPages++;
                    if (!$this->goToNextPage($currentPage)) break;
                    $currentPage++;
                }
            }

        } catch (Exception $e) {
            Log::error("Hopatirparki scraping error: {$e->getMessage()}");
            $this->error("Hopatirparki scraping error: {$e->getMessage()}");
        }

        $this->closeWebDriver();
    }

    private function scrapeMintransData()
    {
        $this->initializeWebDriver();

        try {
            $this->driver->get('https://info.mintrans.uz/#/info/onSearch');
            $wait = new WebDriverWait($this->driver, 15);
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body')));
            $this->info('Mintrans website loaded');

            $processedCount = 0;
            $totalCars = count($this->carNumbers);

            foreach ($this->carNumbers as $carNumber) {
                $processedCount++;
                $this->info("Processing mintrans for plate {$carNumber} ({$processedCount}/{$totalCars})");

                try {
                    $this->driver->navigate()->refresh();
                    $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body')));

                    $input = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
                        WebDriverBy::cssSelector('input[ng-model="object.autoNumber"]')
                    ));
                    $input->clear();
                    $input->sendKeys($carNumber);

                    $searchButton = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
                        WebDriverBy::cssSelector('button[ng-click="object.searchAutoNumber(object.autoNumber)"]')
                    ));
                    $searchButton->click();

                    sleep(5);

                    $mintransData = $this->extractMintransData($wait);

                    if (isset($this->allData[$carNumber])) {
                        $this->allData[$carNumber]['mintrans'] = $mintransData;
                    }

                    $this->info("Mintrans data retrieved for plate: {$carNumber}");
                    Log::info("Mintrans data retrieved: {$carNumber}");

                } catch (Exception $e) {
                    $this->warn("Mintrans scrape error for plate {$carNumber}: {$e->getMessage()}");
                    Log::warning("Mintrans scrape error for {$carNumber}: {$e->getMessage()}");
                    if (isset($this->allData[$carNumber])) {
                        $this->allData[$carNumber]['mintrans'] = [];
                    }
                }

                if ($processedCount % 10 === 0) {
                    sleep(2);
                    $this->info("Pausing after processing {$processedCount} plates");
                }
            }

        } catch (Exception $e) {
            Log::error("Mintrans scraping error: {$e->getMessage()}");
            $this->error("Mintrans scraping error: {$e->getMessage()}");
        }

        $this->closeWebDriver();
    }

    private function extractMintransData($wait)
    {
        try {
            $dataDiv = $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::id('data3')
            ));

            $data = [];

            try {
                $h4 = $dataDiv->findElement(WebDriverBy::tagName('h4'));
                $h4Text = $h4->getText();
                if (preg_match('/Rusumi: (.*?)\s*Yuk ko\'tarish qobiliyati:\s*(.*?)$/', $h4Text, $matches)) {
                    $data['rusumi'] = trim($matches[1]);
                    $data['yuk_qobiliyati'] = trim($matches[2]);
                }
            } catch (Exception $e) {
                Log::warning("h4 data extraction error: {$e->getMessage()}");
            }

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
                        $data['faoliyat_turi'] = $cells[4]->getText();
                        $data['transport_turi'] = $cells[5]->getText();
                        $data['yuk_turi'] = $cells[6]->getText();
                        $data['berilgan_sana'] = $cells[7]->getText();
                        $data['amal_muddati'] = $cells[8]->getText();
                        $data['holati'] = $cells[9]->getText();
                        $data['hududiy_boshqarma'] = $cells[10]->getText();

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
                            Log::warning("Phone number extraction error: {$e->getMessage()}");
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning("Table data extraction error: {$e->getMessage()}");
            }

            return $data;

        } catch (Exception $e) {
            Log::warning("Mintrans data extraction error: {$e->getMessage()}");
            return [];
        }
    }

    private function saveAllData()
    {
        $this->saveToExcel();
        $this->saveToDatabase();
    }

    private function saveToExcel()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                'A1' => 'Tartib raqami',
                'B1' => 'Kirish tartib raqami',
                'C1' => 'Avtomobil raqami',
                'D1' => 'Sana',
                'E1' => 'Kirish joyi',
                'F1' => 'Rusumi',
                'G1' => 'Yuk ko\'tarish qobiliyati',
                'H1' => 'Litsenziya varaqasi',
                'I1' => 'Davlat raqami',
                'J1' => 'Korxona nomi',
                'K1' => 'Telefon raqami',
                'L1' => 'Faoliyat turi',
                'M1' => 'Transport turi',
                'N1' => 'Yuk turi',
                'O1' => 'Berilgan sana',
                'P1' => 'Amal qilish muddati',
                'Q1' => 'Holati',
                'R1' => 'Hududiy boshqarma'
            ];

            foreach ($headers as $cell => $header) {
                $sheet->setCellValue($cell, $header);
            }

            $row = 2;
            foreach ($this->allData as $plaka => $data) {
                $hopatirparki = $data['hopatirparki'];
                $mintrans = $data['mintrans'];

                $sheet->setCellValue('A' . $row, $hopatirparki['sira'] ?? '');
                $sheet->setCellValue('B' . $row, $hopatirparki['giris'] ?? '');
                $sheet->setCellValue('C' . $row, $hopatirparki['plaka'] ?? '');
                $sheet->setCellValue('D' . $row, $hopatirparki['tarih'] ?? '');
                $sheet->setCellValue('E' . $row, $hopatirparki['yer'] ?? '');
                $sheet->setCellValue('F' . $row, $mintrans['rusumi'] ?? '');
                $sheet->setCellValue('G' . $row, $mintrans['yuk_qobiliyati'] ?? '');
                $sheet->setCellValue('H' . $row, $mintrans['license'] ?? '');
                $sheet->setCellValue('I' . $row, $mintrans['state_number'] ?? '');
                $sheet->setCellValue('J' . $row, $mintrans['company'] ?? '');
                $sheet->setCellValueExplicit('K' . $row, $mintrans['phone_number'] ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue('L' . $row, $mintrans['faoliyat_turi'] ?? '');
                $sheet->setCellValue('M' . $row, $mintrans['transport_turi'] ?? '');
                $sheet->setCellValue('N' . $row, $mintrans['yuk_turi'] ?? '');
                $sheet->setCellValue('O' . $row, $mintrans['berilgan_sana'] ?? '');
                $sheet->setCellValue('P' . $row, $mintrans['amal_muddati'] ?? '');
                $sheet->setCellValue('Q' . $row, $mintrans['holati'] ?? '');
                $sheet->setCellValue('R' . $row, $mintrans['hududiy_boshqarma'] ?? '');

                $row++;
            }

            // Fayl nomini yangi format bilan yaratish: turkiya-gruziya-date
            $fileName = 'turkiya-gruziya-' . date('Y-m-d-H-i-s') . '.xlsx';
            $filePath = storage_path("app/public/{$fileName}");

            if (!is_writable(storage_path('app/public'))) {
                throw new Exception('Storage directory is not writable: ' . storage_path('app/public'));
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            // Fayl yo'lini property ga saqlash
            $this->excelFilePath = $filePath;

            $this->info("Excel file saved: {$filePath}");
            Log::info("Excel file created: {$filePath}");

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

        } catch (Exception $e) {
            Log::error("Excel save error: {$e->getMessage()}");
            $this->error("Excel save error: {$e->getMessage()}");
        }
    }

    private function saveToDatabase()
    {
        try {
            foreach ($this->allData as $plaka => $data) {
                $hopatirparki = $data['hopatirparki'];
                $mintrans = $data['mintrans'];

                DB::table('turkey_data')->updateOrInsert(
                    ['plaka' => $plaka],
                    [
                        'sira' => $hopatirparki['sira'] ?? null,
                        'giris' => $hopatirparki['giris'] ?? null,
                        'plaka' => $hopatirparki['plaka'] ?? null,
                        'tarih' => $hopatirparki['tarih'] ?? null,
                        'yer' => $hopatirparki['yer'] ?? null,
                        'rusumi' => $mintrans['rusumi'] ?? null,
                        'yuk_qobiliyati' => $mintrans['yuk_qobiliyati'] ?? null,
                        'license' => $mintrans['license'] ?? null,
                        'state_number' => $mintrans['state_number'] ?? null,
                        'company' => $mintrans['company'] ?? null,
                        'phone_number' => $mintrans['phone_number'] ?? null,
                        'faoliyat_turi' => $mintrans['faoliyat_turi'] ?? null,
                        'transport_turi' => $mintrans['transport_turi'] ?? null,
                        'yuk_turi' => $mintrans['yuk_turi'] ?? null,
                        'berilgan_sana' => $mintrans['berilgan_sana'] ?? null,
                        'amal_muddati' => $mintrans['amal_muddati'] ?? null,
                        'holati' => $mintrans['holati'] ?? null,
                        'hududiy_boshqarma' => $mintrans['hududiy_boshqarma'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );

                $this->info("Database entry saved for plate: {$plaka}");
                Log::info("Database entry saved: {$plaka}");
            }

            $this->info("Saved " . count($this->allData) . " entries to database");

        } catch (Exception $e) {
            Log::error("Database save error: {$e->getMessage()}");
            $this->error("Database save error: {$e->getMessage()}");
        }
    }

    private function isUzbekVehicle($plaka)
    {
        $plaka = strtoupper(trim($plaka));
        $plaka = preg_replace('/[^A-Z0-9]/', '', $plaka);

        foreach ($this->uzbekPatterns as $pattern) {
            if (preg_match($pattern, $plaka)) {
                return true;
            }
        }

        return false;
    }

    private function goToNextPage($currentPage)
    {
        try {
            $paginationSelectors = [
                '.dataTables_paginate #myTable_next:not(.disabled) a',
                '.paginate_button.next:not(.disabled) a'
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
                            $this->info("Navigated to page " . ($currentPage + 1));
                            return true;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->info("No next page button found or it is disabled");
            return false;

        } catch (Exception $e) {
            Log::warning("Pagination error: {$e->getMessage()}");
            $this->warn("Pagination error: {$e->getMessage()}");
            return false;
        }
    }

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

            $this->info('WebDriver initialized');
            Log::info('WebDriver initialized');

        } catch (Exception $e) {
            Log::error("WebDriver initialization error: {$e->getMessage()}");
            $this->error("WebDriver initialization error: {$e->getMessage()}");
            throw $e;
        }
    }

    private function closeWebDriver()
    {
        try {
            if ($this->driver) {
                $this->driver->quit();
                $this->driver = null;
                $this->info('WebDriver closed');
                Log::info('WebDriver closed');
            }
        } catch (Exception $e) {
            Log::error("WebDriver close error: {$e->getMessage()}");
            $this->error("WebDriver close error: {$e->getMessage()}");
        }
    }
}