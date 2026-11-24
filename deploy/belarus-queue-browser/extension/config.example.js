// Nusxa: cp config.example.js config.js — va qiymatlarni to'ldiring (config.js git'ga kirmaydi).
self.CONFIG = {
  // Laravel endpoint (routes/api.php)
  ingestUrl: 'https://scrape.izisol.uz/api/belarus-queue',
  // serverdagi .env: BELARUS_QUEUE_TOKEN bilan bir xil
  token: 'BU_YERGA_BELARUS_QUEUE_TOKEN',
  // mon.declarant.by/#/zone/<slug> — saytda zonani tanlaganda manzil satrida ko'rinadi
  zones: ['benyakoni', 'kamennii-log'],
  // necha daqiqada bir yangilash (ko'pi bilan har 30 daqiqa — saytga yuklama bermaslik uchun)
  intervalMinutes: 30,
  // har zona sahifasi ochilgach javobni shuncha soniya kutish
  zoneWaitSeconds: 20,
};
