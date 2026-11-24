// 1) relay.js dan kelgan navbat javobini serverga yuboradi (X-Ingest-Token bilan).
// 2) Har CONFIG.intervalMinutes daqiqada sahifani zonalar bo'yicha aylantiradi —
//    sayt o'zi yangi ma'lumot so'raydi. Captcha qayta chiqsa, ma'lumot kelmaydi,
//    server buni sezib Telegram'ga "captcha'ni yeching" havolasini yuboradi.
importScripts('config.js');

const zoneUrl = (slug) => `https://mon.declarant.by/#/zone/${slug}`;
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

chrome.runtime.onMessage.addListener((message) => {
  if (message && message.type === 'belarus-queue') ingest(message.text);
});

async function ingest(text) {
  try {
    const response = await fetch(CONFIG.ingestUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Ingest-Token': CONFIG.token },
      body: text,
    });
    const body = await response.json().catch(() => ({}));
    console.log('[belarus-queue]', response.status, body);
  } catch (error) {
    console.warn('[belarus-queue] serverga yuborilmadi', error);
  }
}

async function queueTab() {
  const [tab] = await chrome.tabs.query({ url: 'https://mon.declarant.by/*' });
  return tab || chrome.tabs.create({ url: zoneUrl(CONFIG.zones[0]) });
}

async function cycle() {
  const tab = await queueTab();
  for (const slug of CONFIG.zones) {
    const url = zoneUrl(slug);
    const current = (await chrome.tabs.get(tab.id)).url;
    // bir xil manzil — sahifa o'zi qayta so'ramaydi, shuning uchun yangilaymiz
    current === url ? await chrome.tabs.reload(tab.id) : await chrome.tabs.update(tab.id, { url });
    await sleep(CONFIG.zoneWaitSeconds * 1000);
  }
}

function schedule() {
  chrome.alarms.create('belarus-queue-cycle', { delayInMinutes: 1, periodInMinutes: CONFIG.intervalMinutes });
}

chrome.runtime.onInstalled.addListener(schedule);
chrome.runtime.onStartup.addListener(schedule);
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'belarus-queue-cycle') cycle().catch((error) => console.warn('[belarus-queue]', error));
});
