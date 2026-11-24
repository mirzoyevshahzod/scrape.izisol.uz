# Belarus navbati — captcha'ni odam yechadigan serverdagi brauzer

belarusborder.by (mon.declarant.by) Cloudflare Turnstile qo'ygani uchun navbat
API'si captcha'siz so'rovga bo'sh javob qaytaradi. Captcha'ni avtomatik
yechmaymiz — uni odam yechadi:

```
Serverdagi Chrome (Xvfb) mon.declarant.by'ni ochib turadi
  └─ kengaytma: sayt o'zi olgan navbat javobini POST /api/belarus-queue ga yuboradi
       └─ belarus:queue-sync (har daqiqa): Excel -> crm:daily-import --only=declarant -> Telegram hisobot
Ma'lumot 60 daqiqadan eskirsa -> Telegram: "🔓 Captcha'ni yechish" tugmasi
  └─ tugma noVNC oynasini ochadi (HTTPS + parol) -> captcha katagini bosasiz -> ma'lumot o'zi ketadi
```

Qoidalar: captcha yechish servislari ishlatilmaydi, kengaytma saytga o'zidan
so'rov yubormaydi (faqat sahifani har 30 daqiqada zonalar bo'yicha yangilaydi),
raqamlar faqat CRM uchun.

## O'rnatish (root, bir martalik)

Loyiha: `/var/www/www-root/data/www/scrape.izisol.uz`

1. Paketlar: `apt install -y xvfb x11vnc novnc websockify fonts-dejavu apache2-utils`
2. Chrome: `wget -O /tmp/chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb && apt install -y /tmp/chrome.deb`
3. Foydalanuvchi: `useradd -m -s /bin/bash belarus && mkdir -p /home/belarus/logs && chown belarus: /home/belarus/logs`
4. Kengaytma: `cp -r deploy/belarus-queue-browser/extension /home/belarus/ && cp /home/belarus/extension/config.example.js /home/belarus/extension/config.js && chown -R belarus: /home/belarus/extension`
5. `.env`ga (token — tasodifiy uzun satr, masalan `openssl rand -hex 32`):
   ```
   BELARUS_QUEUE_TOKEN=...
   BELARUS_QUEUE_VNC_URL=https://scrape.izisol.uz/belarus-vnc/vnc.html?path=belarus-vnc/websockify&autoconnect=true&resize=scale
   ```
   so'ng `php artisan optimize:clear`
6. `/home/belarus/extension/config.js` ichida `token`ni `.env`dagi bilan bir xil qiling.
7. noVNC paroli: `htpasswd -c /etc/nginx/belarus-vnc.htpasswd belarus`
8. nginx: `cp deploy/belarus-queue-browser/belarus-vnc.nginx.conf /etc/nginx/vhosts-includes/belarus-vnc.conf && nginx -t && systemctl reload nginx`
9. supervisor: `cp deploy/belarus-queue-browser/belarus-browser.conf /etc/supervisor/conf.d/ && supervisorctl reread && supervisorctl update`
10. Kengaytmani Chrome'ga bir marta qo'shish — `BELARUS_QUEUE_VNC_URL` ni oching, oynada:
    `chrome://extensions` -> "Developer mode" -> "Load unpacked" -> `/home/belarus/extension`.
    Profil `/home/belarus/chrome-profile`da saqlanadi, qayta ishga tushganda ham qoladi.
11. Oynada mon.declarant.by'da captcha'ni yeching, Бенякони va Каменный Лог'ni oching.
    Каменный Лог manzil satridagi `#/zone/...` qismi `config.js`dagi `zones` bilan bir xil bo'lishi kerak
    (boshqacha bo'lsa — config.js'ni tuzatib, chrome://extensions'da kengaytmani "Reload" qiling).

## Tekshirish

- `php artisan belarus:queue-sync` — "navbatda N ta, o'zbek raqamli M ta" chiqishi kerak (ma'lumot kelgan bo'lsa).
- `supervisorctl status belarus-browser:` — 4 ta dastur RUNNING.
- Chrome kengaytma logi: oynada `chrome://extensions` -> kengaytma -> "service worker" -> Console (`[belarus-queue] 200 {ok: true ...}`).

## Muammolar

- Chrome ishga tushmasa (`/home/belarus/logs/chrome.log`da sandbox xatosi) — `belarus-browser.conf`dagi
  chrome buyrug'iga `--no-sandbox` qo'shib, `supervisorctl update` qiling.
- RAM: Chrome ~300–500 MB oladi. `free -m` da joy kam bo'lsa avval keraksiz konteynerlarni to'xtating.
