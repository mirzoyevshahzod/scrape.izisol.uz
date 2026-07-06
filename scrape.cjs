const { chromium } = require('playwright');

(async () => {
  console.log('▶ Script started');

  const region = process.argv[2];
  if (!region) {
    console.error('❌ Region berilmadi');
    process.exit(1);
  }

  const URLS = {
    benyakoni: 'https://mon.declarant.by/zone/benyakoni',
  };

  const url = URLS[region];
  console.log('▶ URL:', url);

  let browser;

  try {
    console.log('▶ Launching Playwright Chromium...');

    browser = await chromium.launch({
      headless: true,
      timeout: 120000,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--single-process',
      ],
    });

    console.log('✅ Browser launched');

    const page = await browser.newPage();

    console.log('▶ Opening page...');
    await page.goto(url, {
      waitUntil: 'domcontentloaded',
      timeout: 120000,
    });

    console.log('✅ Page opened');

    await page.waitForTimeout(5000);

    const rows = await page.$$('table tbody tr');
    console.log('▶ Rows found:', rows.length);

    for (const row of rows) {
      const cells = await row.$$('td');
      if (cells.length >= 3) {
        console.log('✔ REG:', await cells[2].innerText());
      }
    }

    console.log('🎉 SCRAPING DONE');

  } catch (e) {
    console.error('❌ SCRAPING ERROR');
    console.error(e);
  } finally {
    if (browser) {
      await browser.close();
      console.log('▶ Browser closed');
    }
  }
})();
