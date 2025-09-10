<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Interactions\WebDriverActions as InteractionsWebDriverActions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverActions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class MintransCommand extends Command
{
    protected $signature = 'mintrans:scrape {filePath}';
    protected $description = 'Scrape data from mintrans.uz using Excel file input';

    protected $driver;

    public function handle()
    {
        set_time_limit(0); // Allow long-running processes

        Log::info('MintransCommand: Scrape jarayoni boshlandi');

        try {
            $filePath = $this->argument('filePath');
            Log::info('MintransCommand: Fayl yo‘li o‘qildi: ' . $filePath);
            if (!file_exists($filePath)) {
                Log::error('MintransCommand: Fayl topilmadi: ' . $filePath);
                $this->error('Fayl topilmadi!');
                return 1;
            }
            Log::info('MintransCommand: Fayl topildi: ' . $filePath);

            // Step 1: Excel faylni o‘qish
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $spreadsheet = $reader->load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();
                Log::info('MintransCommand: Excel fayl ochildi, qatorlar soni: ' . $highestRow);
            } catch (\Exception $e) {
                Log::error('MintransCommand: Excel faylni o‘qishda xato: ' . $e->getMessage());
                return 1;
            }

            // Step 2: Natija uchun yangi Excel fayl tayyorlash
            $newSpreadsheet = new Spreadsheet();
            $newSheet = $newSpreadsheet->getActiveSheet();
            $newSheet->setCellValue('A1', 'Rusumi');
            $newSheet->setCellValue('B1', 'Yuk ko\'tarish qobiliyati');
            $newSheet->setCellValue('C1', 'Litsenziya varaqasi');
            $newSheet->setCellValue('D1', 'Davlat raqami');
            $newSheet->setCellValue('E1', 'Korxona nomi');
            $newSheet->setCellValue('F1', 'Telefon raqami');
            $newSheet->setCellValue('G1', 'Faoliyat turi');
            $newSheet->setCellValue('H1', 'Transport turi');
            $newSheet->setCellValue('I1', 'Yuk turi');
            $newSheet->setCellValue('J1', 'Berilgan sana');
            $newSheet->setCellValue('K1', 'Amal qilish muddati');
            $newSheet->setCellValue('L1', 'Holati');
            $newSheet->setCellValue('M1', 'Hududiy boshqarma');
            Log::info('MintransCommand: Excel sarlavhalari yozildi');
            $row = 2; // Natija qatorlari 2-qatordan boshlanadi

            // Step 3: Selenium WebDriver-ni ishga tushirish
            if (!$this->initializeWebDriver()) {
                Log::error('MintransCommand: WebDriver ishga tushmadi, jarayon to‘xtatildi');
                return 1;
            }

            // Implicit wait qo‘shish
            $this->driver->manage()->timeouts()->implicitlyWait(5); // 5 soniya
            Log::info('MintransCommand: Implicit wait o‘rnatildi: 5 soniya');

            // Step 4: Saytga kirish
            try {
                $this->driver->get('https://info.mintrans.uz/#/info/onSearch');

                Log::info('MintransCommand: Saytga kirdi: https://info.mintrans.uz/#/info/onSearch');
                $wait = new WebDriverWait($this->driver, 10);
                $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('body')
                ));
                Log::info('MintransCommand: Sahifa to‘liq yuklandi');
            } catch (\Exception $e) {
                Log::error('MintransCommand: Saytga kirishda xato: ' . $e->getMessage());
                $this->closeWebDriver();
                return 1;
            }

            // Step 5: Har bir qatorni qayta ishlash (for sikli)
            for ($i = 2; $i <= $highestRow; $i++) {
                try {
                    $carNumber = $sheet->getCell('A' . $i)->getValue();
                    if (empty($carNumber)) {
                        Log::warning('MintransCommand: A' . $i . ' qatori bo‘sh, o‘tkazib yuborildi');
                        continue;
                    }
                    Log::info('MintransCommand: Qidiruv uchun avtomobil raqami: ' . $carNumber);

                    // Step 5.1: Sahifani yangilash
                    try {
                        $this->driver->navigate()->refresh();
                        $wait = new WebDriverWait($this->driver, 10);
                        $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::tagName('body')
                        ));
                        Log::info('MintransCommand: Sahifa yangilandi');
                    } catch (\Exception $e) {
                        Log::warning('MintransCommand: Sahifani yangilashda xato: ' . $e->getMessage());
                        $this->closeWebDriver();
                        if (!$this->initializeWebDriver()) {
                            Log::error('MintransCommand: WebDriver qayta ishga tushmadi, qator o‘tkazib yuborildi: ' . $i);
                            continue;
                        }
                        $this->driver->get('https://info.mintrans.uz/#/info/onSearch');
                        $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::tagName('body')
                        ));
                        Log::info('MintransCommand: Sahifa qayta yuklandi');
                    }

                    // Step 5.2: Inputga mashina raqamini yozish
                    try {
                        $input = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
                            WebDriverBy::cssSelector('input[ng-model="object.autoNumber"]')
                        ));
                        $input->clear();
                        $input->sendKeys($carNumber);
                        Log::info('MintransCommand: Inputga mashina raqami yozildi: ' . $carNumber);
                    } catch (\Exception $e) {
                        Log::warning('MintransCommand: Inputni topish yoki yozishda xato: ' . $e->getMessage());
                        continue;
                    }

                    // Step 5.3: Qidiruv tugmasini bosish
                    try {
                        $searchButton = $wait->until(WebDriverExpectedCondition::elementToBeClickable(
                            WebDriverBy::cssSelector('button[ng-click="object.searchAutoNumber(object.autoNumber)"]')
                        ));
                        $searchButton->click();
                        Log::info('MintransCommand: Qidiruv tugmasi bosildi');
                    } catch (\Exception $e) {
                        Log::warning('MintransCommand: Qidiruv tugmasini topish yoki bosishda xato: ' . $e->getMessage());
                        continue;
                    }

                    // Step 5.4: 3 sekund kutish
                    sleep(3);
                    Log::info('MintransCommand: 3 sekund kutildi');

                    // Step 5.5: <div id="data3"> ni topish
                    try {
                        $dataDiv = $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::id('data3')
                        ));
                        Log::info('MintransCommand: Data div topildi');
                    } catch (\Exception $e) {
                        Log::warning('MintransCommand: Data div topilmadi: ' . $e->getMessage());
                        continue;
                    }

                    // Step 5.6: h4 tagidan Rusumi va Yuk ko'tarish qobiliyati olish
                    $rusumi = '';
                    $yukQobiliyati = '';
                    try {
                        $h4 = $dataDiv->findElement(WebDriverBy::tagName('h4'));
                        $h4Text = $h4->getText();
                        Log::info('MintransCommand: h4 matni: ' . $h4Text);
                        if (preg_match('/Rusumi: (.*?)\s*Yuk ko\'tarish qobiliyati:\s*(.*?)$/', $h4Text, $matches)) {
                            $rusumi = trim($matches[1]);
                            $yukQobiliyati = trim($matches[2]);
                            Log::info('MintransCommand: Rusumi topildi: ' . $rusumi);
                            Log::info('MintransCommand: Yuk ko\'tarish qobiliyati topildi: ' . $yukQobiliyati);
                        } else {
                            Log::warning('MintransCommand: h4 matnidan rusumi va yuk qobiliyati ajratilmadi: ' . $h4Text);
                        }
                    } catch (\Exception $e) {
                        Log::warning('MintransCommand: h4 topishda xato: ' . $e->getMessage());
                    }

                    // Step 5.7: Table dan ma'lumotlarni olish
                    $license = '';
                    $stateNumber = '';
                    $company = '';
                    $phoneNumber = '';
                    $faoliyatTuri = '';
                    $transportTuri = '';
                    $yukTuri = '';
                    $berilganSana = '';
                    $amalMuddati = '';
                    $holati = '';
                    $hududiyBoshqarma = '';
                    try {
                        $table = $dataDiv->findElement(WebDriverBy::cssSelector('table.table-bordered.table-striped.table-striped2'));
                        $rows = $table->findElements(WebDriverBy::tagName('tr'));
                        Log::info('MintransCommand: Jadvalda qatorlar soni: ' . count($rows));

                        if (count($rows) > 1) { // 1-qator sarlavha
                            $dataRow = $rows[1];
                            $cells = $dataRow->findElements(WebDriverBy::tagName('td'));
                            Log::info('MintransCommand: Qatorda ustunlar soni: ' . count($cells));

                            if (count($cells) >= 11) {
                                $license = $cells[1]->getText(); // Litsenziya varaqasi
                                $stateNumber = $cells[2]->getText(); // Davlat raqami
                                $company = $cells[3]->getText(); // Korxona nomi
                                $faoliyatTuri = $cells[4]->getText(); // Faoliyat turi
                                $transportTuri = $cells[5]->getText(); // Transport turi
                                $yukTuri = $cells[6]->getText(); // Yuk turi
                                $berilganSana = $cells[7]->getText(); // Berilgan sana
                                $amalMuddati = $cells[8]->getText(); // Amal qilish muddati
                                $holati = $cells[9]->getText(); // Holati
                                $hududiyBoshqarma = $cells[10]->getText(); // Hududiy boshqarma

                                // Telefon raqamini olish
                                $phoneNumber = '';
                                try {
                                    // Hover effekti simulyatsiya qilish
                                    $actions = new InteractionsWebDriverActions($this->driver);
                                    $actions->moveToElement($cells[3])->perform(); // Hover
                                    usleep(3000000); // 3 soniya kutish
                                    Log::info('MintransCommand: Korxona nomi ustuniga hover qilindi');

                                    // JavaScript orqali tooltipni majburan ko‘rsatish
                                    $this->driver->executeScript("
                                        let element = document.querySelector('td[title=\"Korxona nomi\"] span.tooltip2');
                                        if (element) element.style.display = 'block';
                                    ");
                                    Log::info('MintransCommand: Tooltip JavaScript orqali ko‘rsatildi');

                                    // Tooltip elementini olish
                                    $tooltip = $cells[3]->findElement(WebDriverBy::cssSelector('span.tooltip2 em'));
                                    $tooltipText = $tooltip->getText();
                                    Log::info('MintransCommand: Tooltip matni: ' . $tooltipText);

                                    // Regex orqali telefon raqamini ajratib olish
                                    if (preg_match('/(?:\+?998|\b998)?[- ]?\d{2}[- ]?\d{3}[- ]?\d{2}[- ]?\d{2}\b/', $tooltipText, $matches)) {
                                        $phoneNumber = $matches[0];
                                        Log::info('MintransCommand: Telefon raqami topildi: ' . $phoneNumber);
                                    } else {
                                        Log::warning('MintransCommand: Telefon raqami topilmadi');
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('MintransCommand: Telefon raqamini olishda xato: ' . $e->getMessage());
                                }


                                Log::info('MintransCommand: Ma‘lumotlar topildi: ' . json_encode([
                                    'license' => $license,
                                    'stateNumber' => $stateNumber,
                                    'company' => $company,
                                    'phoneNumber' => $phoneNumber,
                                    'faoliyatTuri' => $faoliyatTuri,
                                    'transportTuri' => $transportTuri,
                                    'yukTuri' => $yukTuri,
                                    'berilganSana' => $berilganSana,
                                    'amalMuddati' => $amalMuddati,
                                    'holati' => $holati,
                                    'hududiyBoshqarma' => $hududiyBoshqarma
                                ]));
                            } else {
                                Log::warning('MintransCommand: Jadvalda yetarli ustun yo‘q');
                            }
                        } else {
                            Log::warning('MintransCommand: Natija jadvali topilmadi yoki qatorlar yo‘q');
                        }
                    } catch (\Exception $e) {
                        Log::warning('MintransCommand: Jadvaldan ma‘lumot olishda xato: ' . $e->getMessage());
                    }

                    // Step 5.8: Ma‘lumotlarni Excelga yozish
                    $newSheet->setCellValue('A' . $row, $rusumi);
                    $newSheet->setCellValue('B' . $row, $yukQobiliyati);
                    $newSheet->setCellValue('C' . $row, $license);
                    $newSheet->setCellValue('D' . $row, $stateNumber);
                    $newSheet->setCellValue('E' . $row, $company);
                    $newSheet->setCellValueExplicit('F' . $row, $phoneNumber, DataType::TYPE_STRING);
                    $newSheet->setCellValue('G' . $row, $faoliyatTuri);
                    $newSheet->setCellValue('H' . $row, $transportTuri);
                    $newSheet->setCellValue('I' . $row, $yukTuri);
                    $newSheet->setCellValue('J' . $row, $berilganSana);
                    $newSheet->setCellValue('K' . $row, $amalMuddati);
                    $newSheet->setCellValue('L' . $row, $holati);
                    $newSheet->setCellValue('M' . $row, $hududiyBoshqarma);
                    Log::info('MintransCommand: Ma‘lumotlar Excelga yozildi qator uchun: ' . $row);

                    // Har bir qatordan so‘ng faylni saqlash
                    $this->saveExcelFile($newSpreadsheet, storage_path('app/public/results/results.xlsx'));
                    Log::info('MintransCommand: Natija fayli saqlandi qator uchun: ' . $i);

                    $row++;

                    // Xotirani tozalash
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                        Log::info('MintransCommand: Xotira tozalandi');
                    }
                } catch (\Exception $e) {
                    Log::warning('MintransCommand: Qatorni qayta ishlashda xato: ' . $e->getMessage());
                    continue;
                }
            }

            // Step 6: Natija jildini yaratish
            try {
                $outputDir = storage_path('app/public/results');
                if (!file_exists($outputDir)) {
                    mkdir($outputDir, 0755, true);
                    Log::info('MintransCommand: Natija jildi yaratildi: ' . $outputDir);
                }
            } catch (\Exception $e) {
                Log::error('MintransCommand: Natija jildini yaratishda xato: ' . $e->getMessage());
                $this->closeWebDriver();
                return 1;
            }

            // Step 7: Yakuniy faylni saqlash
            try {
                $this->saveExcelFile($newSpreadsheet, storage_path('app/public/results/results.xlsx'));
                Log::info('MintransCommand: Yakuniy natija fayli saqlandi: ' . storage_path('app/public/results/results.xlsx'));
            } catch (\Exception $e) {
                Log::error('MintransCommand: Yakuniy natija faylini saqlashda xato: ' . $e->getMessage());
                $this->closeWebDriver();
                return 1;
            }

            // Step 8: Selenium brauzerni yopish
            $this->closeWebDriver();
            $this->info('Excel fayl muvaffaqiyatli qayta ishlandi!');
            Log::info('MintransCommand: Jarayon muvaffaqiyatli yakunlandi');
            return 0;

        } catch (\Exception $e) {
            Log::error('MintransCommand: Umumiy xato: ' . $e->getMessage());
            $this->error('Xato yuz berdi: ' . $e->getMessage());
            $this->closeWebDriver();
            return 1;
        }
    }

    /**
     * WebDriver-ni ishga tushirish
     * @return bool
     */
    private function initializeWebDriver()
    {
        try {
            $host = 'http://localhost:4444/wd/hub';
            $capabilities = DesiredCapabilities::chrome();
            $this->driver = RemoteWebDriver::create($host, $capabilities);
            Log::info('MintransCommand: Selenium WebDriver ishga tushirildi, host: ' . $host);
            return true;
        } catch (\Exception $e) {
            Log::error('MintransCommand: Selenium WebDriver ulanishda xato: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * WebDriver-ni yopish
     */
    private function closeWebDriver()
    {
        try {
            if ($this->driver) {
                $this->driver->quit();
                Log::info('MintransCommand: Selenium brauzeri yopildi');
            }
        } catch (\Exception $e) {
            Log::error('MintransCommand: Selenium brauzerni yopishda xato: ' . $e->getMessage());
        }
        $this->driver = null;
    }

    /**
     * Excel faylni saqlash
     * @param Spreadsheet $spreadsheet
     * @param string $outputPath
     */
    private function saveExcelFile($spreadsheet, $outputPath)
    {
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);
            Log::info('MintransCommand: Natija fayli saqlandi: ' . $outputPath);
        } catch (\Exception $e) {
            Log::error('MintransCommand: Natija faylini saqlashda xato: ' . $e->getMessage());
        }
    }
}
