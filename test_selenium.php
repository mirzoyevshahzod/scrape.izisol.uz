<?php

require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;

$host = 'http://localhost:4444/wd/hub';

$options = new ChromeOptions();
$options->addArguments(['--headless', '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu']);
$capabilities = DesiredCapabilities::chrome();
$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

echo "Connecting to Selenium...\n";

try {
    $driver = RemoteWebDriver::create($host, $capabilities, 60000, 60000);
    echo "✅ Connected successfully!\n";

    $driver->get('https://example.com');
    echo "Page title: " . $driver->getTitle() . "\n";

    $driver->quit();
    echo "Browser closed successfully.\n";
} catch (Throwable $e) {
    echo "❌ Connection error: " . $e->getMessage() . "\n";
}
