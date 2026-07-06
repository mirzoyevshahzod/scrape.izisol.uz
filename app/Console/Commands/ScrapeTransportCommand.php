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

class ScrapeTransportCommand extends Command
{
    protected $signature = 'scrape:mintrans {start_id} {count} {end_id}';
    protected $description = 'Scrape transport data from e-ombor and mintrans.uz based on start_id and count';

    public function handle()
    {
        $this->info('Starting transport data scraping...');

        $driver = null;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;
        $startTransitId = $this->argument('start_id');
        $count = (int)$this->argument('count');
        $endTransitId = $this->argument('end_id');
        $filePath = storage_path("app/mintrans-{$startTransitId}-{$endTransitId}.xlsx");
        $transportData = []; // Transport raqamlari uchun array

        try {
            // 1. Excel faylga tayyorgarlik
            $this->info('Preparing Excel headings...');
            $headings = [
                'Tranzit ID',
                'Transport Number',
                'Litsenziya varaqasi',
                'Davlat raqami',
                'Korxona nomi',
                'Faoliyat turi',
                'Transport turi',
                'Yuk turi',
                'Berilgan sana',
                'Amal qilish muddati',
                'Holati',
                'Hududiy boshqarma'
            ];
            foreach ($headings as $colIndex => $heading) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($columnLetter . $rowIndex, $heading);
            }
            $rowIndex++;
            $this->info('Headings prepared successfully.');

            // 2. Selenium bilan Chrome brauzerni ishga tushurish
            $this->info('Initializing WebDriver...');
            $host = 'http://localhost:4444/wd/hub';
            $capabilities = DesiredCapabilities::chrome();
            $driver = RemoteWebDriver::create($host, $capabilities);
            $this->info('WebDriver initialized successfully.');

            // 3. e-ombor saytiga kirish va autentifikatsiyadan o‘tish
            $this->info('Navigating to https://e-ombor.customs.uz/...');
            $driver->get('https://e-ombor.customs.uz/');
            $this->info('Site navigation successful.');

            $this->info('Clicking lload button...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('lload'))
            )->click();
            $this->info('lload button clicked.');

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

            $this->info('Clicking sign-in button...');
            $driver->findElement(WebDriverBy::cssSelector('a.sign-in'))->click();
            $this->info('Sign-in button clicked.');

            $this->info('Waiting for URL change to /uzOmbor/indexUzOmbor.jsp...');
            $driver->wait(60)->until(function ($driver) {
                return strpos($driver->getCurrentURL(), '/uzOmbor/indexUzOmbor.jsp') !== false;
            });
            $this->info('URL changed successfully.');

            // 4. Tranzit qidiruv bo‘limiga o‘tish
            $this->info('Opening search menu...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('a.has-arrow'))
            )->click();
            $this->info('Search menu opened.');

            $this->info('Navigating to transit section...');
            $driver->wait(10)->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('a[onclick="MainUzOmbor(507)"]'))
            )->click();
            $this->info('Transit section navigated.');

            // 6. Foydalanuvchi kiritgan count qadar transport raqamlarini olish
            $this->info("Scraping from {$startTransitId} to {$endTransitId} (Count: {$count})...");
            for ($i = 0; $i < $count; $i++) {
                $currentTransitId = $this->generateNextTransitId($startTransitId, $i);
                $this->info("Processing ID #{$i}: {$currentTransitId}...");

                $atrwInput = $driver->wait(10)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('ATRW'))
                );
                $atrwInput->clear();
                $atrwInput->sendKeys($currentTransitId);
                $this->info("ID entered successfully.");

                // Loader yo‘qolishini kutamiz
                try {
                    $this->info("Waiting for loader to disappear...");
                    $driver->wait(15)->until(
                        WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('.loader-wrapper-offcanvas'))
                    );
                    $this->info("Loader disappeared.");
                } catch (Exception $e) {
                    $this->warn("Loader elementi topilmadi yoki allaqachon yo‘q: " . $e->getMessage());
                }

                $this->info("Clicking search button...");
                $searchButton = $driver->wait(10)->until(
                    WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('button[onclick="qidirishEtranzit()"]'))
                );
                $searchButton->click();
                $this->info("Search button clicked.");

                try {
                    $this->info("Checking for alert...");
                    $driver->wait(3)->until(
                        WebDriverExpectedCondition::alertIsPresent()
                    );
                    $alert = $driver->switchTo()->alert();
                    $this->warn("Alert detected: " . $alert->getText());
                    $alert->accept();
                    sleep(2);
                    $this->info("Alert handled, moving to next ID.");
                    continue;
                } catch (Exception $e) {
                    $this->info("No alert detected.");
                }

                sleep(6);

                $this->info("Waiting for table to load...");
                $driver->wait(15)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('#win table'))
                );
                $this->info("Table loaded successfully.");

                $rows = $driver->findElements(WebDriverBy::cssSelector('#win table tbody tr'));
                $this->info("Found " . count($rows) . " rows in table.");

                foreach ($rows as $row) {
                    $cells = $row->findElements(WebDriverBy::cssSelector('td'));
                    $countCells = count($cells);
                    $this->info("Row #{$i} has {$countCells} cells.");

                    if ($countCells > 4) {
                        $transportNumber = trim($cells[4]->getText()) ?? '';
                        $this->info("Transport number found: {$transportNumber}");
                        if (!empty($transportNumber)) {
                            $transportData[] = [
                                'tranzit_id' => $currentTransitId,
                                'transport_number' => $transportNumber
                            ];
                            $this->info("Added to transportData: {$currentTransitId} - {$transportNumber}");
                        }
                    } else {
                        $this->warn("Row #{$i} has insufficient cells ({$countCells}), skipping.");
                    }
                }
            }

            if (empty($transportData)) {
                $this->warn('No transport numbers found.');
                return;
            }

            // 7-9. mintrans.uz’da qidiruv va Excel’ga saqlash
            $this->info('Navigating to https://info.mintrans.uz/#/info/onSearch...');
            $driver->get('https://info.mintrans.uz/#/info/onSearch');
            $this->info('Navigation to mintrans.uz successful.');

            foreach ($transportData as $item) {
                $currentTransitId = $item['tranzit_id'];
                $transportNumber = $item['transport_number'];
                $this->info("Processing transport number: {$transportNumber} (Tranzit ID: {$currentTransitId})...");

                try {
                    $this->info("Finding input field for transport number...");
                    $inputField = $driver->wait(10)->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('input[ng-model="object.autoNumber"]'))
                    );
                    $inputField->clear();
                    $inputField->sendKeys($transportNumber);
                    $this->info("Transport number entered: {$transportNumber}");

                    $this->info("Clicking search button on mintrans.uz...");
                    $searchButton = $driver->wait(10)->until(
                        WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('button[ng-click="object.searchAutoNumber(object.autoNumber)"]'))
                    );
                    $searchButton->click();
                    $this->info("Search button clicked.");

                    sleep(3);
                    $this->info("Waiting 3 seconds for results...");

                    $tableRows = $driver->findElements(WebDriverBy::cssSelector('table.table-bordered tbody tr'));
                    $this->info("Found " . count($tableRows) . " rows in mintrans table.");

                    if (count($tableRows) > 0) {
                        $firstRow = $tableRows[0];
                        $cells = $firstRow->findElements(WebDriverBy::cssSelector('td'));
                        $this->info("Processing " . count($cells) . " cells in first row.");

                        $data = [
                            $currentTransitId, // Tranzit ID
                            $transportNumber, // Transport Number
                        ];
                        foreach ($cells as $cell) {
                            $cellText = trim($cell->getText()) ?? '';
                            $data[] = $cellText;
                            $this->info("Cell text: {$cellText}");
                        }

                        $this->info("Writing data to Excel for transport number: {$transportNumber}...");
                        foreach ($data as $colIndex => $value) {
                            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                            $sheet->setCellValue($columnLetter . $rowIndex, $value);
                        }
                        $rowIndex++;
                        $this->info("Data written successfully.");
                    } else {
                        $this->warn("No data found for transport number: {$transportNumber}");
                    }
                } catch (Exception $e) {
                    $this->error("Error for transport number {$transportNumber}: " . $e->getMessage());
                    $this->info("Stack trace: " . $e->getTraceAsString());
                    continue;
                }
            }

            // 9. Excel faylini saqlash
            $this->info('Saving Excel file...');
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $this->info('Excel file saved successfully: ' . $filePath);

            if (!file_exists($filePath) || filesize($filePath) == 0) {
                throw new Exception('Error saving file: ' . $filePath);
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