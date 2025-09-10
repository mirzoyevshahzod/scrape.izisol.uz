<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Chrome\ChromeOptions;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Console\Command;
use Exception;

class ScrapeDeclarantData extends Command
{
    protected $signature = 'scrape:declarant {region} {--url=} {--name=}';
    protected $description = 'Universal scraper for all Declarant zones - extracts Uzbek vehicles data';

    // O'zbek mashina raqamlari pattern-lari
    private $uzbekPatterns = [
        // 01X###XX formatida
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)[A-Z]\d{3}[A-Z]{2}$/',
        // 01###XXX formatida  
        '/^(01|10|20|25|30|40|50|60|70|75|80|85|90|95)\d{3}[A-Z]{3}$/',
    ];

    public function handle()
    {
        $region = $this->argument('region');
        $url = $this->option('url');
        $name = $this->option('name');
        
        $this->info("Starting Declarant data scraping process for region: {$region}");
        $this->info("Target URL: {$url}");

        $driver = null;
        $spreadsheet = null;

        try {
            // Initialize variables
            $this->info('Initializing variables...');
            $fileName = "declarant-{$name}-" . date('Y-m-d-H-i-s') . ".xlsx";
            $filePath = storage_path("app/{$fileName}");
            $this->info("Variables initialized: region={$region}, name={$name}, filePath={$filePath}");

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

            // Set up Excel headers
            $this->info('Setting up Excel headers...');
            $headings = [
                'Порядок вызова',
                'Тип очереди', 
                'Рег.номер',
                'Дата регистрации в ЗО',
                'Статус изменен',
                'Статус'
            ];
            
            foreach ($headings as $colIndex => $heading) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($columnLetter . $rowIndex, $heading);
                $this->info("Set header at column {$columnLetter}{$rowIndex}: {$heading}");
            }
            $rowIndex++;
            $this->info('Excel headers successfully written. Current rowIndex: ' . $rowIndex);

            // Initialize WebDriver with Chrome options
            $this->info('Initializing WebDriver...');
            $host = 'http://localhost:4444/wd/hub';
            $chromeOptions = new ChromeOptions();
            $chromeOptions->addArguments([
                '--no-sandbox', 
                '--disable-dev-shm-usage', 
                '--disable-gpu',
                '--window-size=1920,1080',
                '--disable-blink-features=AutomationControlled',
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]);
            
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
            
            $driver = RemoteWebDriver::create($host, $capabilities);
            $this->info('WebDriver successfully initialized.');

            // Navigate to the specific region URL
            $this->info("Navigating to {$url}...");
            $driver->get($url);
            $this->info('Waiting for page to load (8 seconds)...');
            sleep(8);
            $this->info('Website successfully loaded.');

            // Wait for Angular/JavaScript to be fully loaded
            $this->info('Waiting for page content to fully load...');
            try {
                $driver->wait(20)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::cssSelector('table.cdk-table tbody tr')
                    )
                );
                $this->info('Table content loaded successfully.');
            } catch (Exception $e) {
                $this->warn("Table loading check failed: {$e->getMessage()}. Attempting to continue...");
                
                // Additional wait and retry
                sleep(5);
                try {
                    $testRows = $driver->findElements(WebDriverBy::cssSelector('table.cdk-table tbody tr'));
                    $this->info("Found " . count($testRows) . " rows after retry.");
                } catch (Exception $e2) {
                    $this->warn("Second attempt failed: {$e2->getMessage()}");
                }
            }

            // Additional wait for dynamic content
            sleep(3);

            $processedCount = 0;
            $totalUzbekVehicles = 0;

            // Main scraping loop with pagination support
            $maxPages = 15; // Maximum pages to scrape
            $currentPage = 1;
            $consecutiveEmptyPages = 0;

            while ($currentPage <= $maxPages && $consecutiveEmptyPages < 3) {
                $this->info("Processing page {$currentPage}...");
                $pageUzbekCount = 0;

                try {
                    // Wait for table to be present with multiple selectors
                    $tableFound = false;
                    $tableSelectors = [
                        'table.cdk-table tbody',
                        'table tbody',
                        '.table__wrapper table tbody'
                    ];

                    foreach ($tableSelectors as $selector) {
                        try {
                            $driver->wait(5)->until(
                                WebDriverExpectedCondition::presenceOfElementLocated(
                                    WebDriverBy::cssSelector($selector)
                                )
                            );
                            $tableFound = true;
                            $this->info("Table found with selector: {$selector}");
                            break;
                        } catch (Exception $e) {
                            continue;
                        }
                    }

                    if (!$tableFound) {
                        $this->warn("Table not found with any selector on page {$currentPage}");
                        break;
                    }

                    // Get all rows from the table with multiple selectors
                    $this->info('Extracting table rows...');
                    $rows = [];
                    $rowSelectors = [
                        'table.cdk-table tbody tr.cdk-row',
                        'table tbody tr',
                        '.table__wrapper table tbody tr'
                    ];

                    foreach ($rowSelectors as $selector) {
                        try {
                            $rows = $driver->findElements(WebDriverBy::cssSelector($selector));
                            if (count($rows) > 0) {
                                $this->info("Found rows with selector: {$selector}");
                                break;
                            }
                        } catch (Exception $e) {
                            continue;
                        }
                    }

                    $this->info("Found " . count($rows) . " rows on page {$currentPage}.");

                    if (count($rows) == 0) {
                        $consecutiveEmptyPages++;
                        $this->warn("No data found on page {$currentPage}. Empty pages count: {$consecutiveEmptyPages}");
                        
                        if ($consecutiveEmptyPages >= 3) {
                            $this->warn("Found 3 consecutive empty pages. Ending pagination.");
                            break;
                        }
                        
                        // Try to go to next page anyway
                        if (!$this->goToNextPage($driver, $currentPage)) {
                            break;
                        }
                        $currentPage++;
                        continue;
                    } else {
                        $consecutiveEmptyPages = 0; // Reset empty page counter
                    }

                    // Process each row
                    foreach ($rows as $rowIdx => $row) {
                        $this->info("Processing row " . ($rowIdx + 1) . " on page {$currentPage}...");
                        
                        try {
                            // Try different cell selectors
                            $cells = [];
                            $cellSelectors = [
                                'td.cdk-cell',
                                'td',
                                '.table__cell'
                            ];

                            foreach ($cellSelectors as $selector) {
                                try {
                                    $cells = $row->findElements(WebDriverBy::cssSelector($selector));
                                    if (count($cells) >= 6) {
                                        break;
                                    }
                                } catch (Exception $e) {
                                    continue;
                                }
                            }

                            if (count($cells) < 6) {
                                $this->warn("Insufficient columns in row " . ($rowIdx + 1) . " (found " . count($cells) . "), skipping.");
                                continue;
                            }

                            // Extract cell data
                            $orderNumber = trim($cells[0]->getText());
                            $queueType = trim($cells[1]->getText());
                            $regNumber = trim($cells[2]->getText());
                            $registrationDate = trim($cells[3]->getText());
                            $statusChanged = trim($cells[4]->getText());
                            $status = trim($cells[5]->getText());

                            // Clean status text (remove circle indicators)
                            $status = preg_replace('/^\s*\w*\s*/', '', $status);
                            $status = trim($status);

                            $this->info("Extracted data: Order={$orderNumber}, Queue={$queueType}, RegNum={$regNumber}");

                            // Check if registration number matches Uzbek patterns
                            $isUzbekVehicle = $this->isUzbekVehicle($regNumber);
                            
                            if ($isUzbekVehicle) {
                                $this->info("✓ Found Uzbek vehicle: {$regNumber}");
                                
                                // Write to Excel
                                $sheet->setCellValue('A' . $rowIndex, $orderNumber);
                                $sheet->setCellValue('B' . $rowIndex, $queueType);
                                $sheet->setCellValue('C' . $rowIndex, $regNumber);
                                $sheet->setCellValue('D' . $rowIndex, $registrationDate);
                                $sheet->setCellValue('E' . $rowIndex, $statusChanged);
                                $sheet->setCellValue('F' . $rowIndex, $status);

                                $rowIndex++;
                                $totalUzbekVehicles++;
                                $pageUzbekCount++;
                                $this->info("Uzbek vehicle data written to Excel. Row: {$rowIndex}, Total Uzbek vehicles: {$totalUzbekVehicles}");
                            } else {
                                $this->info("✗ Non-Uzbek vehicle: {$regNumber} - skipping");
                            }

                        } catch (Exception $e) {
                            $this->warn("Error processing row " . ($rowIdx + 1) . ": {$e->getMessage()}");
                        }

                        $processedCount++;
                    }

                    $this->info("Page {$currentPage} processed. Found {$pageUzbekCount} Uzbek vehicles on this page.");

                    // Try to go to next page
                    if (!$this->goToNextPage($driver, $currentPage)) {
                        $this->info("No more pages available. Ending pagination.");
                        break;
                    }

                    $currentPage++;

                } catch (Exception $e) {
                    $this->warn("Error processing page {$currentPage}: {$e->getMessage()}");
                    $consecutiveEmptyPages++;
                    
                    if ($consecutiveEmptyPages >= 3) {
                        break;
                    }
                    
                    // Try to continue to next page
                    if (!$this->goToNextPage($driver, $currentPage)) {
                        break;
                    }
                    $currentPage++;
                }

                // Safety break to prevent infinite loops
                if ($currentPage > $maxPages) {
                    $this->warn("Reached maximum page limit ({$maxPages}). Stopping pagination.");
                    break;
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
            
            $fileSize = filesize($filePath);
            $this->info("Excel file verified: Exists and is non-empty (Size: {$fileSize} bytes).");
            $this->info("Total processed rows: {$processedCount}");
            $this->info("Total Uzbek vehicles found: {$totalUzbekVehicles}");
            $this->info("Total pages processed: " . ($currentPage - 1));

            if ($totalUzbekVehicles == 0) {
                $this->warn('No Uzbek vehicles found in the data!');
            } else {
                $this->info("SUCCESS: Found {$totalUzbekVehicles} Uzbek vehicles in region {$region}");
            }

            $this->info('Declarant data scraping completed successfully!');

        } catch (Exception $e) {
            $this->error("Critical error occurred: {$e->getMessage()}");
            $this->error("Error file: {$e->getFile()}");
            $this->error("Error line: {$e->getLine()}");
            $this->info("Stack trace: {$e->getTraceAsString()}");
        } finally {
            // Clean up WebDriver
            if ($driver) {
                $this->info('Closing WebDriver...');
                try {
                    $driver->quit();
                    $this->info('WebDriver successfully closed.');
                } catch (Exception $e) {
                    $this->warn("Error closing WebDriver: {$e->getMessage()}");
                }
            }

            // Clean up spreadsheet resources
            if (isset($spreadsheet)) {
                $this->info('Cleaning up spreadsheet resources...');
                try {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                    $this->info('Spreadsheet resources successfully cleaned up.');
                } catch (Exception $e) {
                    $this->warn("Error cleaning up spreadsheet: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Check if vehicle registration number matches Uzbek patterns
     */
    private function isUzbekVehicle($regNumber)
    {
        $regNumber = strtoupper(trim($regNumber));
        
        // Remove any spaces or special characters
        $regNumber = preg_replace('/[^A-Z0-9]/', '', $regNumber);
        
        foreach ($this->uzbekPatterns as $pattern) {
            if (preg_match($pattern, $regNumber)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Attempt to navigate to next page
     */
    private function goToNextPage($driver, $currentPage)
    {
        $this->info('Looking for pagination controls...');
        
        try {
            // Multiple pagination strategies
            $paginationStrategies = [
                // Strategy 1: Common next button selectors
                [
                    'selectors' => [
                        'button[aria-label="Next page"]',
                        '.pagination .next:not(.disabled)',
                        '.mat-paginator-next-button:not([disabled])',
                        'button.next-page:not([disabled])',
                        '[data-testid="next-page"]',
                        '.pagination-next:not(.disabled)'
                    ],
                    'method' => 'click'
                ],
                // Strategy 2: Page number links
                [
                    'selectors' => [
                        '.pagination a[data-page="' . ($currentPage + 1) . '"]',
                        '.page-link:contains("' . ($currentPage + 1) . '")'
                    ],
                    'method' => 'click'
                ],
                // Strategy 3: Generic pagination
                [
                    'selectors' => [
                        '.pagination li:last-child a:not(.disabled)',
                        '.pager .next a'
                    ],
                    'method' => 'click'
                ]
            ];

            foreach ($paginationStrategies as $strategy) {
                foreach ($strategy['selectors'] as $selector) {
                    try {
                        $elements = $driver->findElements(WebDriverBy::cssSelector($selector));
                        
                        foreach ($elements as $element) {
                            if ($element->isDisplayed() && $element->isEnabled()) {
                                $this->info("Found pagination element with selector: {$selector}");
                                
                                // Scroll to element
                                $driver->executeScript("arguments[0].scrollIntoView(true);", [$element]);
                                sleep(1);
                                
                                // Click the element
                                $element->click();
                                $this->info("Clicked next page button successfully");
                                
                                // Wait for page to load
                                sleep(4);
                                
                                // Verify page changed
                                $this->info("Waiting for next page to load...");
                                return true;
                            }
                        }
                    } catch (Exception $e) {
                        // Continue to next selector
                        continue;
                    }
                }
            }

            // Strategy 4: JavaScript-based pagination
            try {
                $this->info("Attempting JavaScript-based pagination...");
                
                $jsCommands = [
                    "document.querySelector('button[aria-label=\"Next page\"]')?.click()",
                    "document.querySelector('.pagination .next:not(.disabled)')?.click()",
                    "document.querySelector('.mat-paginator-next-button:not([disabled])')?.click()"
                ];

                foreach ($jsCommands as $js) {
                    try {
                        $result = $driver->executeScript("return " . $js . " || false");
                        if ($result) {
                            $this->info("JavaScript pagination successful");
                            sleep(4);
                            return true;
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
            } catch (Exception $e) {
                $this->warn("JavaScript pagination failed: {$e->getMessage()}");
            }

            $this->info("No pagination controls found or all methods failed.");
            return false;

        } catch (Exception $e) {
            $this->warn("Pagination error: {$e->getMessage()}");
            return false;
        }
    }
}