<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverActions;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Console\Command;
use Exception;
use Facebook\WebDriver\Interactions\WebDriverActions as InteractionsWebDriverActions;

class ScrapeMintransData extends Command
{
    protected $signature = 'scrape:mintrans:license {start_id} {count} {end_id}';
    protected $description = 'Scrape mintrans.uz data and export to Excel with detailed logging (PHP 8.3 compatible)';

    public function handle()
    {
        $this->info('Starting Mintrans data scraping process...');

        $driver = null;
        $spreadsheet = null;

        try {
            // Initialize variables
            $this->info('Initializing variables...');
            $startId = (int)$this->argument('start_id');
            $count = (int)$this->argument('count');
            $endId = (int)$this->argument('end_id');
            $fileName = "mintrans-{$startId}-{$endId}.xlsx";
            $filePath = storage_path("app/{$fileName}");
            $this->info("Variables initialized: start_id={$startId}, count={$count}, end_id={$endId}, filePath={$filePath}");

            // Check storage directory permissions
            $this->info('Checking storage directory permissions...');
            if (!is_writable(storage_path('app'))) {
                throw new Exception('Storage directory is not writable!');
            }
            $this->info('Storage directory is writable.');

            // Initialize spreadsheet
            $this->info('Creating new spreadsheet instance...');
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $rowIndex = 1;
            $this->info('Spreadsheet instance created successfully.');

            // Set up Excel headers (only required columns)
            $this->info('Setting up Excel headers...');
            $headings = [
                'License Number',
                'State Number',
                'Company Name',
                'Company Address',
                'Company Phone',
                'Activity Type Name',
                'Transport Type',
                'Cargo Type',
                'Issue Date',
                'Expiry Date',
                'Status',
                'Regional Office',
                'Vehicle Model',
                'Seat Count / Load Capacity'
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
            $driver = RemoteWebDriver::create($host, $capabilities);
            $this->info('WebDriver successfully initialized.');

            // Navigate to the website
            $this->info('Navigating to https://info.mintrans.uz/#/info/onSearch...');
            $driver->get('https://info.mintrans.uz/#/info/onSearch');
            $this->info('Waiting for page to load (3 seconds)...');
            sleep(3);
            $this->info('Website successfully loaded.');

            // Wait for Angular to be fully loaded
            $this->info('Waiting for Angular to fully load...');
            try {
                $driver->wait(10)->until(
                    function () use ($driver) {
                        $readyState = $driver->executeScript('return document.readyState');
                        $angularReady = $driver->executeScript('return angular.element(document).injector().get(\'$http\').pendingRequests.length === 0');
                        $this->info("Document readyState: {$readyState}, Angular requests pending: " . ($angularReady ? 'None' : 'Some'));
                        return $readyState === 'complete' && $angularReady;
                    }
                );
                $this->info('Angular application fully loaded.');
            } catch (Exception $e) {
                $this->warn("Angular loading check failed: {$e->getMessage()}. Continuing anyway.");
            }

            // Select radio button (JavaScript first)
            $this->info('Attempting to select radio button (selectFour) via JavaScript first...');
            try {
                $radioButton = $driver->executeScript("return document.getElementById('selectFour');");
                if ($radioButton) {
                    $this->info('selectFour element found via JavaScript, clicking...');
                    $driver->executeScript("document.getElementById('selectFour').click();");
                    $this->info('Waiting for JavaScript click action to complete (1 second)...');
                    sleep(1);
                    $this->info('Radio button successfully clicked via JavaScript.');
                } else {
                    throw new Exception('selectFour not found via JS.');
                }
            } catch (Exception $e) {
                $this->warn("JavaScript click failed: {$e->getMessage()}. Falling back to WebDriverWait.");
                $radioButton = $driver->wait(10)->until(
                    WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('selectFour'))
                );
                $this->info('selectFour element found via WebDriver, clicking...');
                $radioButton->click();
                $this->info('Waiting for radio button action to complete (1 second)...');
                sleep(1);
                $this->info('Radio button successfully clicked via WebDriver.');
            }

            // Start scraping loop
            $this->info("Starting scraping loop from ID {$startId} to {$endId}...");
            $processedCount = 0;

            for ($currentId = $startId; $currentId >= $endId; $currentId--) {
                $this->info("Processing ID {$currentId} (Progress: {$processedCount}/{$count})...");

                try {
                    // Enter ID into input field
                    $this->info("Locating input field for ID {$currentId}...");
                    $inputField = $driver->wait(10)->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::cssSelector('input[ng-model="object.lcNumber"]')
                        )
                    );
                    $this->info('Input field located, clearing previous content...');
                    $inputField->clear();
                    $this->info("Entering ID {$currentId} into input field...");
                    $inputField->sendKeys($currentId);
                    $this->info('Waiting for input to register (1 second)...');
                    sleep(1);

                    // Click search button
                    $this->info('Locating search button...');
                    $searchButton = $driver->wait(10)->until(
                        WebDriverExpectedCondition::elementToBeClickable(
                            WebDriverBy::cssSelector('button[ng-click="object.searchLcNumber(object.lcNumber)"]')
                        )
                    );
                    $this->info('Search button located, clicking...');
                    $searchButton->click();
                    $this->info('Waiting for search results to load (3 seconds)...');
                    sleep(3);

                    // Extract vehicle information
                    $vehicleModel = '';
                    $seatCount = '';
                    $this->info('Extracting vehicle information...');
                    try {
                        $vehicleInfo = $driver->findElement(
                            WebDriverBy::cssSelector('h4.text-center.center')
                        );
                        $vehicleText = $vehicleInfo->getText();
                        $this->info("Vehicle info text: {$vehicleText}");

                        if (preg_match('/Rusumi:\s*(.+?)\s*O\'rindiqlar soni:/', $vehicleText, $matches)) {
                            $vehicleModel = trim($matches[1]);
                            $this->info("Extracted vehicle model: {$vehicleModel}");
                        } else {
                            $this->warn('Vehicle model not found in text.');
                        }

                        if (preg_match('/O\'rindiqlar soni:\s*([^\n]+)/', $vehicleText, $matches)) {
                            $seatCount = trim($matches[1]);
                            $this->info("Extracted seat count: {$seatCount}");
                        } elseif (preg_match('/yuk ko\'tarish qobiliyati:\s*([^\n]+)/', $vehicleText, $matches)) {
                            $seatCount = trim($matches[1]);
                            $this->info("Extracted load capacity: {$seatCount}");
                        } else {
                            $this->warn('Seat count or load capacity not found in text.');
                        }
                    } catch (Exception $e) {
                        $this->warn("Failed to extract vehicle information: {$e->getMessage()}");
                    }

                    // Extract table data
                    $this->info('Waiting for table data to load...');
                    $driver->wait(10)->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::cssSelector('table.table-bordered tbody')
                        )
                    );
                    $this->info('Table located, extracting rows...');
                    $rows = $driver->findElements(
                        WebDriverBy::cssSelector('table.table-bordered tbody tr')
                    );
                    $this->info("Found " . count($rows) . " rows in table.");

                    if (count($rows) == 0) {
                        $this->warn("No data found for ID {$currentId}.");
                        $processedCount++;
                        continue;
                    }

                    // Process each row
                    foreach ($rows as $rowIdx => $row) {
                        $this->info("Processing table row " . ($rowIdx + 1) . "...");
                        $cells = $row->findElements(WebDriverBy::cssSelector('td'));

                        if (count($cells) < 10) {
                            $this->warn("Insufficient columns in row " . ($rowIdx + 1) . ", skipping.");
                            continue;
                        }

                        // Extract cell data
                        $this->info('Extracting cell data...');
                        $data = [];
                        foreach ($cells as $cellIndex => $cell) {
                            $cellText = trim($cell->getText());
                            $data[] = $cellText;
                            $this->info("Cell {$cellIndex}: {$cellText}");
                        }

                        // Extract company information with improved tooltip extraction
                        $this->info('Extracting company information...');
                        $companyInfo = $data[3] ?? '';
                        $companyAddress = '';
                        $companyPhone = '';
                        $companyName = $companyInfo;

                        try {
                            $companyCell = $cells[3];

                            // Hover over company cell
                            $actions = new InteractionsWebDriverActions($driver);
                            $actions->moveToElement($companyCell)->perform();
                            $this->info('Hovered over company cell to trigger tooltip.');
                            sleep(1); // Wait for tooltip to appear

                            // Force tooltip visibility with JS
                            $driver->executeScript("
                                let tooltip = arguments[0].querySelector('span.tooltip2');
                                if (tooltip) tooltip.style.display = 'block';
                            ", [$companyCell]);
                            $this->info('Tooltip forced to display with JS.');

                            // Get tooltip text
                            $tooltipElement = $companyCell->findElement(
                                WebDriverBy::cssSelector('span.tooltip2 em')
                            );
                            $tooltipText = $tooltipElement->getText();
                            $this->info("Company tooltip text: {$tooltipText}");

                            // Parse tooltip for address and phone
                            if (preg_match('/([^,]+,[^,]+),\s*(\d+)/', $tooltipText, $matches)) {
                                $companyAddress = trim($matches[1]);
                                $companyPhone = trim($matches[2]);
                                $this->info("Extracted company address: {$companyAddress}");
                                $this->info("Extracted company phone: {$companyPhone}");
                            } else if (preg_match('/(?:\+?998|\b998)?[- ]?\d{2}[- ]?\d{3}[- ]?\d{2}[- ]?\d{2}\b/', $tooltipText, $matches)) {
                                $companyPhone = $matches[0];
                                $companyAddress = trim(str_replace($companyPhone, '', $tooltipText));
                                $this->info("Extracted full formatted phone: {$companyPhone}");
                                $this->info("Extracted company address: {$companyAddress}");
                            } else {
                                $this->warn('Failed to parse company tooltip.');
                            }

                            $mainText = $companyCell->getText();
                            $companyName = trim(str_replace($tooltipText, '', $mainText));
                            $this->info("Extracted company name: {$companyName}");
                        } catch (Exception $e) {
                            $this->warn("Failed to extract company tooltip: {$e->getMessage()}. Using fallback.");
                        }

                        // Extract activity type name (no code)
                        $activityTypeName = $data[4] ?? '';
                        $this->info("Extracted activity type name: {$activityTypeName}");

                        // Extract license number (no date)
                        $licenseNumber = $data[1] ?? '';
                        $this->info("Extracted license number: {$licenseNumber}");

                        // Write to Excel (only required columns)
                        $this->info("Writing data to Excel for row {$rowIndex}...");
                        $sheet->setCellValue('A' . $rowIndex, $licenseNumber);
                        $this->info("Written License Number: {$licenseNumber}");
                        $sheet->setCellValue('B' . $rowIndex, $data[2] ?? '');
                        $this->info("Written State Number: " . ($data[2] ?? ''));
                        $sheet->setCellValue('C' . $rowIndex, $companyName);
                        $this->info("Written Company Name: {$companyName}");
                        $sheet->setCellValue('D' . $rowIndex, $companyAddress);
                        $this->info("Written Company Address: {$companyAddress}");
                        $sheet->setCellValueExplicit('E' . $rowIndex, $companyPhone, DataType::TYPE_STRING);
                        $this->info("Written Company Phone: {$companyPhone}");
                        $sheet->setCellValue('F' . $rowIndex, $activityTypeName);
                        $this->info("Written Activity Type Name: {$activityTypeName}");
                        $sheet->setCellValue('G' . $rowIndex, $data[5] ?? '');
                        $this->info("Written Transport Type: " . ($data[5] ?? ''));
                        $sheet->setCellValue('H' . $rowIndex, $data[6] ?? '');
                        $this->info("Written Cargo Type: " . ($data[6] ?? ''));
                        $sheet->setCellValue('I' . $rowIndex, $data[7] ?? '');
                        $this->info("Written Issue Date: " . ($data[7] ?? ''));
                        $sheet->setCellValue('J' . $rowIndex, $data[8] ?? '');
                        $this->info("Written Expiry Date: " . ($data[8] ?? ''));
                        $sheet->setCellValue('K' . $rowIndex, $data[9] ?? '');
                        $this->info("Written Status: " . ($data[9] ?? ''));
                        $sheet->setCellValue('L' . $rowIndex, $data[10] ?? '');
                        $this->info("Written Regional Office: " . ($data[10] ?? ''));
                        $sheet->setCellValue('M' . $rowIndex, $vehicleModel);
                        $this->info("Written Vehicle Model: {$vehicleModel}");
                        $sheet->setCellValue('N' . $rowIndex, $seatCount);
                        $this->info("Written Seat Count / Load Capacity: {$seatCount}");

                        $rowIndex++;
                        $this->info("Data successfully written to Excel. Next rowIndex: {$rowIndex}");
                    }

                } catch (Exception $e) {
                    $this->warn("Error processing ID {$currentId}: {$e->getMessage()}");
                }

                $processedCount++;
                $this->info("Progress: {$processedCount}/{$count} IDs processed.");

                // Pause every 100 IDs
                if ($processedCount % 100 == 0) {
                    $this->info("Processed 100 IDs, pausing for 5 seconds...");
                    sleep(5);
                    $this->info("Resuming after pause.");
                }
            }

            // Save Excel file
            $this->info("Saving Excel file to {$filePath}...");
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $this->info('Excel file successfully saved.');

            // Verify file existence and size
            $this->info('Verifying Excel file...');
            if (!file_exists($filePath) || filesize($filePath) == 0) {
                throw new Exception('Failed to create or save Excel file: File is missing or empty.');
            }
            $this->info("Excel file verified: Exists and is non-empty (Size: " . filesize($filePath) . " bytes).");

            $this->info('Mintrans data scraping completed successfully!');

        } catch (Exception $e) {
            $this->error("Critical error occurred: {$e->getMessage()}");
            $this->info("Stack trace: {$e->getTraceAsString()}");
        } finally {
            // Clean up WebDriver
            if ($driver) {
                $this->info('Closing WebDriver...');
                $driver->quit();
                $this->info('WebDriver successfully closed.');
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