<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Exception;

class ScrapeEomborData extends Command
{
    protected $signature = 'scrape:eombor {start_id} {count} {end_id}';
    protected $description = 'Scrape e-ombor data and export to Excel based on start_id and count';

    public function handle()
    {
        $this->info('Starting e-ombor data scraping...');

        $driver = null;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;
        $startTransitId = $this->argument('start_id');
        $count = (int)$this->argument('count');
        $endTransitId = $this->argument('end_id');
        $filePath = storage_path("app/e-ombor-{$startTransitId}-{$endTransitId}.xlsx");

        try {
            // 1. Selenium WebDriver'ni ishga tushirish
            $this->info('Initializing WebDriver...');
            $host = 'http://localhost:4444/wd/hub';
            $capabilities = DesiredCapabilities::chrome();
            $driver = RemoteWebDriver::create($host, $capabilities);
            $this->info('WebDriver initialized successfully.');

            // 2. Saytga kirish
            $this->info('Navigating to https://e-ombor.customs.uz/...');
            $driver->get('https://e-ombor.customs.uz/');
            $this->info('Site navigation successful.');

            // 3. Kirish tugmasi
            $this->info('Clicking lload button...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('lload'))
            )->click();
            $this->info('lload button clicked.');

            // 4. Sertifikat tanlash
            $this->info('Selecting certificate dropdown...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('.dropdown-toggle'))
            )->click();
            $this->info('Dropdown clicked.');

            $this->info('Selecting certificate option...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('a[onclick^="uiComboSelect"]'))
            )->click();
            $this->info('Certificate option selected.');

            // 5. "Kirish" tugmasi va modal
            $this->info('Clicking sign-in button...');
            $driver->findElement(WebDriverBy::cssSelector('a.sign-in'))->click();
            $this->info('Sign-in button clicked.');

            // 6. URL o‘zgarishini kutish (modal qo‘l bilan to‘ldiriladi)
            $this->info('Waiting for URL change to /uzOmbor/indexUzOmbor.jsp...');
            $driver->wait(60)->until(function ($driver) {
                return strpos($driver->getCurrentURL(), '/uzOmbor/indexUzOmbor.jsp') !== false;
            });
            $this->info('URL changed successfully.');

            // 7. Qidiruv menyusiga kirish
            $this->info('Opening search menu...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('a.has-arrow'))
            )->click();
            $this->info('Search menu opened.');

            // 8. Tranzit bo‘limiga o‘tish
            $this->info('Navigating to transit section...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('a[onclick="MainUzOmbor(507)"]'))
            )->click();
            $this->info('Transit section navigated.');

            // 9. Sarlavhalarni yozish (12 ta ustun)
            $this->info('Writing Excel headings...');
            $headings = [
                'Document Number',
                'Custom Code',
                'Custom Date',
                'TEBHN Number',
                'Transport Number',
                'Gross Weight',
                'INN',
                'Recipient Name',
                'Delivery Post',
                'Delivery Date',
                'Arrival Place',
                'Status'
            ];
            foreach ($headings as $colIndex => $heading) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($columnLetter . $rowIndex, $heading);
            }
            $rowIndex++;
            $this->info('Headings written successfully.');

            // 11. Foydalanuvchi kiritgan count qadar datani yig‘ish
            $endTransitId = $this->generateNextTransitId($startTransitId, $count); // Oxirgi ID
            $this->info("Scraping from {$startTransitId} to {$endTransitId} (Count: {$count})...");

            for ($j = 0; $j < $count; $j++) {
                $currentTransitId = $this->generateNextTransitId($startTransitId, $j);
                $this->info("Processing ID #{$j}: {$currentTransitId}...");

                try {
                    $this->info("Finding ATRW input for ID: {$currentTransitId}...");
                    $atrwInput = $driver->wait(10)->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('ATRW'))
                    );
                    $atrwInput->clear();
                    $atrwInput->sendKeys($currentTransitId);
                    $this->info("ID entered successfully.");

                    $this->info("Clicking search button...");
                    $searchButton = $driver->wait(10)->until(
                        WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('button[onclick="qidirishEtranzit()"]'))
                    );
                    $searchButton->click();
                    $this->info("Search button clicked.");

                    // Alertni tekshirish
                    try {
                        $this->info("Checking for alert...");
                        $alert = $driver->switchTo()->alert();
                        $this->warn("Skip: Alert detected - {$alert->getText()}");
                        $alert->accept();
                        sleep(5);
                        $this->info("Alert handled, moving to next ID.");
                        continue;
                    } catch (Exception $e) {
                        $this->info("No alert detected.");
                    }

                    sleep(5);
                    $this->info("Waiting for table to load...");
                    $driver->wait(15)->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('#win table'))
                    );
                    $this->info("Table loaded successfully.");

                    $rows = $driver->findElements(WebDriverBy::cssSelector('#win table tbody tr'));
                    $this->info("Found " . count($rows) . " rows in table."); // Sonini aniq ko‘rsatish

                    foreach ($rows as $row) {
                        $cells = $row->findElements(WebDriverBy::cssSelector('td'));
                        $countCells = count($cells);
                        $this->info("Row #{$j} has {$countCells} cells.");

                        if ($countCells === 1) {
                            $firstCellText = trim($cells[0]->getText());
                            $this->info("First cell text: '{$firstCellText}'");
                            if ($firstCellText === "Жадвал бўш" || $firstCellText === "Маълумот топилмади") {
                                $this->warn("Skip: ID #{$currentTransitId} da ma’lumot topilmadi - '{$firstCellText}'");
                                continue 2; // Ichki sikldan chiqish
                            }
                        }

                        $data = [];
                        foreach ($cells as $colIndex => $cell) {
                            $cellText = trim($cell->getText()) ?? null;
                            $data[] = $cellText;
                            $this->info("Cell #{$colIndex} text: '{$cellText}'");
                        }

                        // Recipient Name'dan INN va Recipient Name'ni ajratish
                        $recipientData = $data[6] ?? '';
                        $inn = '';
                        $recipientName = '';
                        if (preg_match('/^(\d+)\s+(.*)$/', $recipientData, $matches)) {
                            $inn = $matches[1];
                            $recipientName = $matches[2];
                        } else {
                            $recipientName = $recipientData;
                        }
                        $this->info("INN: '{$inn}', Recipient Name: '{$recipientName}'");

                        // INN uzunligini tekshirish (faqat 9 ta raqam)
                        if (empty($inn) || strlen($inn) !== 9) {
                            $this->warn("Skip: ID #{$currentTransitId} uchun INN xato - uzunligi 9 emas yoki bo'sh");
                            continue 2; // Ichki sikldan chiqish
                        }

                        // 12 ta ustunga ma’lumotlarni yozish
                        $this->info("Writing data to Excel for ID: {$currentTransitId}...");
                        $sheet->setCellValue('A' . $rowIndex, $data[0] ?? ''); // Document Number
                        $sheet->setCellValue('B' . $rowIndex, $data[1] ?? ''); // Custom Code
                        $sheet->setCellValue('C' . $rowIndex, $data[2] ?? ''); // Custom Date
                        $sheet->setCellValue('D' . $rowIndex, $data[3] ?? ''); // TEBHN Number
                        $sheet->setCellValue('E' . $rowIndex, $data[4] ?? ''); // Transport Number
                        $sheet->setCellValue('F' . $rowIndex, $data[5] ?? ''); // Gross Weight
                        $sheet->setCellValue('G' . $rowIndex, $inn ?? '');     // INN
                        $sheet->setCellValue('H' . $rowIndex, $recipientName ?? ''); // Recipient Name
                        $sheet->setCellValue('I' . $rowIndex, $data[7] ?? ''); // Delivery Post
                        $sheet->setCellValue('J' . $rowIndex, $data[8] ?? ''); // Delivery Date
                        $sheet->setCellValue('K' . $rowIndex, $data[9] ?? ''); // Arrival Place
                        $sheet->setCellValue('L' . $rowIndex, $data[10] ?? ''); // Status
                        $rowIndex++;
                        $this->info("Data written successfully for ID: {$currentTransitId}.");
                    }
                } catch (Exception $e) {
                    $this->warn("Skip: Error at ID #{$j} ({$currentTransitId}) - {$e->getMessage()}");
                    $this->info("Stack trace: " . $e->getTraceAsString());
                    continue; // Xatolik bo‘lsa, keyingi qidiruvga o‘tish
                }
            }

            // 12. Excel faylini saqlash
            $this->info('Saving Excel file...');
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $this->info('Excel file saved successfully: ' . $filePath);

            if (!file_exists($filePath) || filesize($filePath) == 0) {
                throw new Exception('Fayl yozishda xatolik yuzaga keldi: ' . $filePath);
            }

            $this->info('Scraping process completed.');
        } catch (Exception $e) {
            $this->error('Error occurred: ' . $e->getMessage());
            $this->info("Stack trace: " . $e->getTraceAsString());
        } finally {
            if ($driver) {
                $this->info('Closing WebDriver...');
                $driver->quit();
                $this->info('WebDriver closed.');
            }
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $this->info('Spreadsheet resources cleared.');
            }
        }
    }

    // Tranzit ID'dan keyingi ID'larni generatsiya qilish
    private function generateNextTransitId($startTransitId, $increment)
    {
        $prefix = substr($startTransitId, 0, 2); // "AT"
        $year = substr($startTransitId, 2, 4);   // "2025"
        $number = (int)substr($startTransitId, 6); // "0346677"

        $newNumber = $number + $increment;
        $newNumberPadded = str_pad($newNumber, 7, '0', STR_PAD_LEFT); // 7 xonali qilish

        return $prefix . $year . $newNumberPadded;
    }
}