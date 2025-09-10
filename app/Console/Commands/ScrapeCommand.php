<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class ScrapeCommand extends Command
{
    protected $signature = 'scrape';
    protected $description = 'Scrape Uzbek license plates from hopatirparki.com and export to Excel with detailed logging (PHP 8.3 compatible)';

    public function handle()
    {
        $this->info('Starting scraping process for Uzbek license plates...');

        $driver = null;
        $spreadsheet = null;

        try {
            // Initialize variables
            $this->info('Initializing variables...');
            $fileName = 'uzbek_plates.xlsx';
            $filePath = storage_path("app/{$fileName}");
            $this->info("Variables initialized: filePath={$filePath}");

            // Check storage directory permissions
            $this->info('Checking storage directory permissions...');
            if (!is_writable(storage_path('app'))) {
                throw new Exception('Storage directory is not writable! Please run: icacls .\storage /grant Everyone:F /T');
            }
            $this->info('Storage directory is writable.');

            // Initialize spreadsheet
            $this->info('Creating new spreadsheet instance...');
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $rowIndex = 1;
            $this->info('Spreadsheet instance created successfully.');

            // Set up Excel headers
            $this->info('Setting up Excel headers...');
            $headings = [
                'Tartib raqami',
                'Kirish tartib raqami',
                'Avtomobil raqami',
                'Sana',
                'Kirish joyi'
            ];
            
            foreach ($headings as $colIndex => $heading) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($columnLetter . $rowIndex, $heading);
                $this->info("Set header at column {$columnLetter}{$rowIndex}: {$heading}");
            }
            $rowIndex++;
            $this->info('Excel headers successfully written. Current rowIndex: ' . $rowIndex);

            // Initialize WebDriver
            $this->info('Initializing WebDriver...');
            $host = 'http://localhost:4444/wd/hub';
            $capabilities = DesiredCapabilities::chrome();
            try {
                $driver = RemoteWebDriver::create($host, $capabilities, 5000, 10000);
                $this->info('WebDriver successfully initialized.');
            } catch (Exception $e) {
                $this->error('Failed to initialize WebDriver: ' . $e->getMessage());
                $this->info('Ensure ChromeDriver is running on port 4444. Command to start: C:\chromedriver\chromedriver.exe --port=4444');
                $this->info('Also, verify ChromeDriver version matches Chrome browser version. Check chrome://version and download from https://sites.google.com/chromium.org/driver/');
                throw new Exception('WebDriver initialization failed: ' . $e->getMessage());
            }

            // Navigate to the website
            $this->info('Navigating to https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp...');
            $driver->get('https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp');
            $this->info('Waiting for page to load (20 seconds)...');
            sleep(20); // Sayt sekin bo'lsa, kutish vaqtini oshirish
            $this->info('Website successfully loaded.');

            // Prepare Uzbek plate regex
            $this->info('Preparing Uzbek plate regex patterns...');
            $prefixes = ['01', '10', '20', '25', '30', '40', '50', '60', '70', '75', '80', '85', '90', '95'];
            $prefixRegex = '(' . implode('|', $prefixes) . ')';
            $regex1 = "/^{$prefixRegex}[A-Z]\d{3}[A-Z]{2}$/"; // e.g., 01A123BC
            $regex2 = "/^{$prefixRegex}\d{3}[A-Z]{3}$/"; // e.g., 01123ABC
            $this->info('Regex patterns prepared: ' . json_encode([$regex1, $regex2]));

            // Total pages ni aniqlash
            $totalPages = 1;
            try {
                $infoElement = $driver->findElement(WebDriverBy::id('myTable_info'));
                $infoText = $infoElement->getText();
                $this->info("Table info text: {$infoText}");
                if (preg_match('/of (\d+) entries/', $infoText, $matches)) {
                    $totalEntries = (int)$matches[1];
                    $entriesPerPage = 10; // Har sahifada 10 ta yozuv
                    $totalPages = ceil($totalEntries / $entriesPerPage);
                    $this->info("Calculated total pages: {$totalPages} (total entries: {$totalEntries})");
                } else {
                    $this->warn('Failed to parse total entries from info text.');
                }
            } catch (Exception $e) {
                $this->warn("Failed to get total pages: {$e->getMessage()}. Assuming dynamic pagination.");
            }

            // Start scraping loop with pagination
            $this->info('Starting scraping loop with pagination...');
            $processedPages = 0;
            $totalDataCount = 0;
            $maxRetries = 3;

            while ($processedPages < $totalPages) {
                $processedPages++;
                $this->info("Processing page {$processedPages}...");

                // Joriy sahifa raqamini loglash
                try {
                    $currentPageElement = $driver->findElement(WebDriverBy::cssSelector('.dataTables_paginate .paginate_button.active'));
                    $currentPage = $currentPageElement->getText();
                    $this->info("Current page number: {$currentPage}");
                } catch (Exception $e) {
                    $this->warn("Failed to get current page number: {$e->getMessage()}");
                }

                // Wait for table to load
                $this->info('Waiting for table to load...');
                try {
                    $driver->wait(30, 500)->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('myTable'))
                    );
                    $this->info('Table loaded successfully.');
                } catch (Exception $e) {
                    $this->error('Failed to load table: ' . $e->getMessage());
                    throw new Exception('Table loading failed: ' . $e->getMessage());
                }

                // Extract rows
                $this->info('Extracting table rows...');
                $rows = $driver->findElements(WebDriverBy::cssSelector('#myTable tbody tr'));
                $this->info("Found " . count($rows) . " rows on page {$processedPages}.");

                if (count($rows) === 0) {
                    $this->warn('No rows found on page ' . $processedPages . '. Possible issue with table loading.');
                }

                foreach ($rows as $rowIdx => $row) {
                    $this->info("Processing row " . ($rowIdx + 1) . " on page {$processedPages}...");
                    $tds = $row->findElements(WebDriverBy::tagName('td'));
                    if (count($tds) >= 5) {
                        $sira = trim($tds[0]->getText());
                        $giris = trim($tds[1]->getText());
                        $plakaFull = trim($tds[2]->getText());
                        $tarih = trim($tds[3]->getText());
                        $yer = trim($tds[4]->getText());

                        // Clean plaka
                        $this->info("Raw plaka text: {$plakaFull}");
                        $plaka = explode("\n", $plakaFull)[0];
                        $plaka = trim(preg_replace('/\s*\(.*\)/', '', $plaka));
                        $this->info("Cleaned plaka: {$plaka}");

                        // Check if Uzbek plate
                        $isUzbek = preg_match($regex1, $plaka) || preg_match($regex2, $plaka);
                        $this->info("Is Uzbek plate: " . ($isUzbek ? 'Yes' : 'No'));

                        if ($isUzbek) {
                            // Write to Excel
                            $this->info("Writing Uzbek plate data to Excel at row {$rowIndex}...");
                            $sheet->setCellValue('A' . $rowIndex, $sira);
                            $this->info("Written Tartib raqami: {$sira}");
                            $sheet->setCellValue('B' . $rowIndex, $giris);
                            $this->info("Written Kirish tartib raqami: {$giris}");
                            $sheet->setCellValue('C' . $rowIndex, $plaka);
                            $this->info("Written Avtomobil raqami: {$plaka}");
                            $sheet->setCellValue('D' . $rowIndex, $tarih);
                            $this->info("Written Sana: {$tarih}");
                            $sheet->setCellValue('E' . $rowIndex, $yer);
                            $this->info("Written Kirish joyi: {$yer}");

                            $rowIndex++;
                            $totalDataCount++;
                            $this->info("Data successfully written. Next rowIndex: {$rowIndex}, Total Uzbek plates: {$totalDataCount}");
                        }
                    } else {
                        $this->warn("Insufficient columns in row " . ($rowIdx + 1) . ", skipping.");
                    }
                }

                if ($processedPages >= $totalPages) {
                    $this->info('Reached estimated total pages. Ending loop.');
                    break;
                }

                // Check and click Next button
                $this->info('Checking for Next button...');
                $nextButtons = $driver->findElements(WebDriverBy::cssSelector('.dataTables_paginate #myTable_next:not(.disabled) a'));
                if (count($nextButtons) === 0) {
                    $this->info('No more pages or Next button disabled. Ending pagination loop.');
                    try {
                        $paginationHtml = $driver->findElement(WebDriverBy::cssSelector('.dataTables_paginate'))->getAttribute('outerHTML');
                        $this->info("Pagination HTML: {$paginationHtml}");
                    } catch (Exception $e) {
                        $this->warn("Failed to get pagination HTML: {$e->getMessage()}");
                    }
                    break;
                }

                $retryCount = 0;
                $pageNavigated = false;
                while ($retryCount < $maxRetries && !$pageNavigated) {
                    $this->info("Attempting to click Next button (Attempt " . ($retryCount + 1) . ")...");
                    try {
                        // Tugma holatini loglash
                        $nextButtonHtml = $nextButtons[0]->getAttribute('outerHTML');
                        $this->info("Next button HTML: {$nextButtonHtml}");

                        // Old info text ni saqlash
                        $oldInfo = $driver->findElement(WebDriverBy::id('myTable_info'))->getText();
                        $this->info("Old info text: {$oldInfo}");

                        // JavaScript orqali bosish
                        $driver->executeScript("arguments[0].click();", [$nextButtons[0]]);
                        $this->info('Waiting for next page to load (20 seconds)...');
                        sleep(20);

                        // Info text ning o'zgarishini kutish
                        $this->info('Waiting for info text to change...');
                        $driver->wait(30, 500)->until(
                            function () use ($driver, $oldInfo) {
                                $newInfo = $driver->findElement(WebDriverBy::id('myTable_info'))->getText();
                                return $newInfo !== $oldInfo;
                            }
                        );

                        $newInfo = $driver->findElement(WebDriverBy::id('myTable_info'))->getText();
                        $this->info("New info text: {$newInfo}");

                        // Joriy sahifa raqamini tekshirish
                        try {
                            $newPageElement = $driver->findElement(WebDriverBy::cssSelector('.dataTables_paginate .paginate_button.active'));
                            $newPageNumber = $newPageElement->getText();
                            $this->info("Navigated to page: {$newPageNumber}");
                        } catch (Exception $e) {
                            $this->warn("Failed to get new page number: {$e->getMessage()}");
                        }

                        $pageNavigated = true;
                    } catch (Exception $e) {
                        $retryCount++;
                        $this->warn("Failed to navigate to next page (Attempt {$retryCount}): {$e->getMessage()}");
                        if ($retryCount >= $maxRetries) {
                            $this->error('Max retries reached for pagination. Ending loop.');
                            try {
                                $paginationHtml = $driver->findElement(WebDriverBy::cssSelector('.dataTables_paginate'))->getAttribute('outerHTML');
                                $this->info("Pagination HTML: {$paginationHtml}");
                            } catch (Exception $e) {
                                $this->warn("Failed to get pagination HTML: {$e->getMessage()}");
                            }
                            break 2; // Tashqi while siklidan chiqish
                        }
                        $this->info('Retrying after 5 seconds...');
                        sleep(5);
                        // Next tugmasini qayta aniqlash
                        $nextButtons = $driver->findElements(WebDriverBy::cssSelector('.dataTables_paginate #myTable_next:not(.disabled) a'));
                        if (count($nextButtons) === 0) {
                            $this->info('No more pages after retry. Ending pagination loop.');
                            break 2;
                        }
                    }
                }
            }

            $this->info("Scraping completed. Total pages processed: {$processedPages}, Total Uzbek plates found: {$totalDataCount}");

            // Save Excel file
            $this->info("Saving Excel file to {$filePath}...");
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $this->info('Excel file successfully saved.');

            // Verify file existence and size
            $this->info('Verifying Excel file...');
            if (!file_exists($filePath)) {
                throw new Exception('Failed to create Excel file: File is missing.');
            }
            $fileSize = filesize($filePath);
            $this->info("Excel file verified: Exists and size is {$fileSize} bytes.");
            if ($fileSize <= 6232) {
                $this->warn('Excel file contains minimal data or only headers.');
            }

            $this->info('Scraping process completed successfully!');

        } catch (Exception $e) {
            $this->error("Critical error occurred: {$e->getMessage()}");
            $this->info("Stack trace: {$e->getTraceAsString()}");
            throw $e; // Controller ga xato yuborish uchun
        } finally {
            // Clean up WebDriver
            if ($driver) {
                $this->info('Closing WebDriver...');
                try {
                    $driver->quit();
                    $this->info('WebDriver successfully closed.');
                } catch (Exception $e) {
                    $this->warn('Failed to close WebDriver: ' . $e->getMessage());
                }
            } else {
                $this->warn('WebDriver was not initialized, skipping closure.');
            }

            // Clean up spreadsheet resources
            if (isset($spreadsheet)) {
                $this->info('Cleaning up spreadsheet resources...');
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $this->info('Spreadsheet resources successfully cleaned up.');
            } else {
                $this->warn('Spreadsheet was not initialized, skipping cleanup.');
            }
        }
    }
}