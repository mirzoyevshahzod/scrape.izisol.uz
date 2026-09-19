# Zanjeer CRM avtomatlashtirish (`crm:login`, `crm:import-custom-operations`, `crm:daily-import`)

Bu hujjat Zanjeer CRM bilan headless Chrome orqali ishlaydigan uchta buyruq serverda ishlamay qolsa, sababini topish va tuzatish uchun:

- **`crm:login`** — CRM'ga kirib `XSRF-TOKEN` va session cookie'ni `crm_tokens` jadvaliga saqlaydi. Bu tokenlardan `scrape:zanjeer-operators` buyrug'i foydalanadi (`app/Models/CrmToken.php`).
- **`crm:import-custom-operations {path}`** — berilgan bitta Excel faylni, fayl nomiga (yoki `--border=`ga) mos "Граница"ni tanlab, "Границы → Торнадо" (`/users/custom-operations`) sahifasidagi "Импортировать" oynasi orqali yuklaydi.
- **`crm:daily-import`** — kunlik pipeline: Qoldau (Kazakhstan-Russia), Turkey, Zitic va Belarus declarant fayllarini tegishli `excel:split`/`excel:border-convert`/`zitic:convert`/`declarant:convert` buyruqlari bilan formatlab, **bitta CRM login sessiyasida** ketma-ket barcha granitsalarga yuklaydi (`app/Console/Commands/CrmDailyImportCommand.php`).

Uchchala buyruq ham umumiy `app/Support/Crm/CrmSession.php` klassidan foydalanadi (chromedriver'ni ishga tushirish, login qilish, import oynasi bilan ishlash — bir joyda).

## `crm:daily-import` qaysi fayllarni qayerga yuklaydi

| Manba | Format buyrug'i | Granitsa(lar) |
|---|---|---|
| `storage/app/qozoq/kazakhstan-russia-*.xlsx` | `excel:split` (zip ichidagi har bir fayl alohida granitsa) | ZIP ichidagi fayl nomidan olinadi (masalan `Алимбет - Орск_2026-09-19.xlsx` → `Алимбет - Орск`) |
| `storage/app/turkey/turkey_scrape-*.xlsx` | `excel:border-convert` | `Хопа-Сарпи` |
| `storage/app/zitic/zitic_*.xlsx` | `zitic:convert` | `Владикавказ — Казбеги` |
| `storage/app/declarant/benyakoni-*.xlsx` | `declarant:convert` | `Бенякони - Шальчининкай` |
| `storage/app/declarant/kamennii-log-*.xlsx` | `declarant:convert` | `Каменный Лог - Мядининкай` |

Har bir granitsa mustaqil ravishda `try/catch` qilinadi — biri xato bersa ham, qolganlari davom etadi. Oxirida barcha granitsalar bo'yicha natija jadvali chiqadi, va agar kamida bittasi xato bo'lsa, buyruq umumiy `FAILURE` bilan tugaydi (lekin muvaffaqiyatli bo'lganlar baribir yuklangan bo'ladi).

`crm:daily-import` scheduler orqali har kuni soat **08:30**da ishga tushadi (`routes/console.php`) — bu scrape (07:00) va `qozoq:warehouse-pipeline` (08:00) tugagandan keyingi vaqt.

## Talab qilinadigan narsalar

- PHP paketlar: `php-webdriver/webdriver`, `symfony/process` (`composer.json`da bor, `composer install` qilingan bo'lishi kerak).
- Serverda **Chrome yoki Chromium** va unga **mos versiyadagi** `chromedriver` o'rnatilgan bo'lishi kerak.
- `.env` faylida quyidagilar to'g'ri to'ldirilgan bo'lishi kerak:

```env
CRM_BASE_URL=https://crm.zanjeer.uz
CRM_LOGIN_EMAIL=...
CRM_LOGIN_PASSWORD=...
CRM_SESSION_COOKIE_NAME=zanjeer_crm_session

CHROMEDRIVER_URL=http://127.0.0.1:9515
CHROMEDRIVER_BINARY=/usr/bin/chromedriver
CHROME_BINARY=/usr/bin/google-chrome
```

`crm:login` scheduler orqali har soatda avtomatik ishga tushadi (`routes/console.php`), chunki CRM sessiyasi ~120 daqiqada tugaydi.

## Qo'lda tekshirish

```bash
php artisan crm:login -v
```

Muvaffaqiyatli bo'lsa oxirida shu chiqadi:

```
chromedriver ishga tushirilmoqda (...)
Login sahifasi ochilmoqda: https://crm.zanjeer.uz/login
Tokenlar muvaffaqiyatli saqlandi (crm_tokens jadvali, name=zanjeer_crm).
```

Tokenlar bazaga tushganini tekshirish:

```bash
php artisan tinker --execute="
\$t = App\Models\CrmToken::where('name','zanjeer_crm')->first();
echo \$t ? 'topildi, fetched_at=' . \$t->fetched_at : 'topilmadi';
"
```

## Tez-tez uchraydigan xatolar va yechimlari

### 1. `session not created: This version of ChromeDriver only supports Chrome version XXX`

Bu — `chromedriver` versiyasi serverdagi Chrome/Chromium versiyasiga mos kelmasligini bildiradi. Ular **bir xil major versiyada** bo'lishi shart.

**Tekshirish:**

```bash
google-chrome --version      # yoki: chromium --version / chromium-browser --version
chromedriver --version
```

**Yechim** — Chrome versiyasiga mos `chromedriver`ni [Chrome for Testing](https://googlechromelabs.github.io/chrome-for-testing/) manzilidan yuklab olish:

```bash
# 1. Chrome versiyasini aniqlang, masalan: 150.0.7871.181
google-chrome --version

# 2. Mos versiyani JSON orqali toping (linux64):
curl -s https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json \
  | grep -A5 '"150\.' | grep linux64

# 3. Topilgan URL'dan zip'ni yuklab, joylashtiring (masalan /opt/chromedriver ga):
sudo mkdir -p /opt/chromedriver
curl -o /tmp/cd.zip "https://storage.googleapis.com/chrome-for-testing-public/<VERSIYA>/linux64/chromedriver-linux64.zip"
sudo unzip -o /tmp/cd.zip -d /tmp
sudo cp /tmp/chromedriver-linux64/chromedriver /opt/chromedriver/chromedriver
sudo chmod +x /opt/chromedriver/chromedriver

# 4. .env da ko'rsating:
# CHROMEDRIVER_BINARY=/opt/chromedriver/chromedriver
```

Agar `sudo`/root huquqi bo'lmasa, `chromedriver`ni foydalanuvchi papkasiga (masalan `~/.local/share/chromedriver/`) joylashtirib, shu yo'lni `CHROMEDRIVER_BINARY`ga yozish yetarli — root huquqi shart emas.

> Eslatma: Ubuntu/Debian'dagi `chromium-browser` ko'pincha snap orqali o'rnatiladi va snap'ning sandbox cheklovlari tufayli headless rejimda ishlamasligi yoki juda sekin/qotib qolishi mumkin. Shu sabab **`google-chrome-stable` (apt orqali, snap emas)** ishlatish tavsiya etiladi.

### 2. Buyruq juda uzoq ishlaydi yoki hech qanday natija chiqmasdan "osilib qoladi"

Bunga odatda ikkita sabab bo'ladi:

- **`chromedriver` porti band** — avvalgi muvaffaqiyatsiz urinishdan qolgan `chromedriver`/`chrome` jarayoni hali ishlab turibdi. Tekshiring:

  ```bash
  ps aux | grep -i chromedriver | grep -v grep
  ps aux | grep -i "headless=new" | grep -v grep
  ```

  Topilgan PID'larni **aniq raqami bilan** o'chiring (keng qamrovli `pkill -f "chrome"` kabi buyruqlardan saqlaning — ular joriy terminal/skriptning o'zini ham to'xtatib qo'yishi mumkin):

  ```bash
  kill -9 <PID>
  ```

- **`chromedriver`ning log chiqishi o'qilmay qolib, ichki pipe to'lib ketishi** — bu holat `app/Support/Crm/CrmSession.php` faylidagi `startChromedriver()` metodida `$process->disableOutput();` qatori orqali oldini olingan (barcha uchta buyruq shu klassdan foydalanadi). Agar kimdir bu qatorni o'chirib qo'ysa yoki fayl qayta yozilsa, xuddi shu muammo qaytishi mumkin — ushbu qator albatta joyida bo'lishi kerak.

### 3. `'zanjeer_crm_session' nomli session cookie topilmadi`

CRM tomonidagi Laravel `session.cookie` nomi o'zgargan bo'lishi mumkin (odatda `APP_NAME`ga bog'liq). Brauzerda CRM saytiga kirib, DevTools → Application → Cookies orqali haqiqiy cookie nomini tekshiring va `.env`dagi `CRM_SESSION_COOKIE_NAME`ni shunga moslang.

### 4. `input[name="email"]` yoki submit tugmasi topilmadi (login sahifasi vaqt tugashi bilan xato beradi)

CRM login sahifasi (Livewire komponent) o'zgargan bo'lishi mumkin. `app/Support/Crm/CrmSession.php` dagi `login()` metodi ichidagi CSS selektorlarni tekshiring va yangilang:

```php
'input[name="email"]'
'input[name="password"]'
'form button[type="submit"]'
```

Haqiqiy HTML strukturasini ko'rish uchun brauzerda login sahifasini oching va elementlarni tekshiring (Inspect Element).

### 5. `CRM_LOGIN_EMAIL va CRM_LOGIN_PASSWORD .env faylida ... berilishi shart`

`.env` faylida bu ikkalasi bo'sh qolgan. To'ldiring va keshni tozalang:

```bash
php artisan config:clear
```

### 6. `scrape:zanjeer-operators` ishlamayapti, lekin `crm:login` muvaffaqiyatli

- Avval `crm_tokens` jadvalida yozuv borligini tekshiring (yuqoridagi tinker buyrug'i orqali).
- `scrape:zanjeer-operators` sessiya eskirganini avtomatik aniqlab, bir marta `crm:login --fresh` orqali qayta login qiladi — agar bu ham ishlamasa, muammo login jarayonining o'zida (yuqoridagi bandlarni tekshiring).

### 7. `'<nom>' nomli granitsa ro'yxatda topilmadi. Ko'rinayotgan variantlar: ...`

`crm:import-custom-operations` yoki `crm:daily-import` CRM'dagi "Граница" qidiruv oynasiga nom yozdi, lekin ro'yxatda **aynan bir xil matnli** variant topilmadi (katta-kichik harf farqi hisobga olinmaydi, lekin bo'sh joy/tire turi (`-` va `—`) muhim). Xabardagi "Ko'rinayotgan variantlar" ro'yxatini haqiqiy CRM'dagi nom bilan solishtiring:

- `crm:daily-import` uchun fixed nomlar `app/Console/Commands/CrmDailyImportCommand.php` faylida (`importTurkey`, `importZitic`, `importDeclarant` metodlarida) — CRM'dagi nom o'zgarsa, shu yerni yangilang.
- `excel:split` orqali kelgan granitsalar uchun (Kazakhstan-Russia) nom to'g'ridan-to'g'ri scrape qilingan Excel'dagi "Chegara nomi" ustunidan olinadi — demak muammo CRM'dagi granitsa nomi bilan Qoldau'dagi chegara nomi turlicha yozilganida bo'ladi (masalan bitta tire o'rniga ikkitasi, yoki so'z tartibi farqi).

Haqiqiy nomni tekshirish uchun CRM saytida qo'lda "Импортировать" oynasini ochib, "Граница" qidiruviga qidirilayotgan so'zni yozib ko'ring.

### 8. Fayl biriktirilmadi / "Сохранить" bosilgandan keyin oyna yopilmaydi

- **Fayl yo'li noto'g'ri**: `crm:import-custom-operations`ga (yoki `crm:daily-import` ichidagi vaqtinchalik konvert qilingan faylga) beriladigan yo'l **shu buyruq ishlayotgan serverning o'zidagi** to'liq (absolute) yo'l bo'lishi kerak — chunki brauzer faylni to'g'ridan-to'g'ri shu serverning diskidan o'qiydi (uzoqdagi Selenium Grid emas). Nisbiy yo'l yoki boshqa serverdagi yo'l ishlamaydi.
- **Oyna yopilmasa** — xato xabari ehtimol CRM validatsiyasidan (masalan noto'g'ri formatdagi Excel, bo'sh fayl). Buyruq xato xabarida oynadagi matnni ko'rsatadi (`Oynadagi matn: ...`) — shu matnga qarab sababni aniqlang.

## Foydali diagnostika buyruqlari

```bash
# chromedriver alohida ishga tushib-tushmasligini tekshirish
/opt/chromedriver/chromedriver --port=9516 &
curl -s http://127.0.0.1:9516/status
kill %1

# scheduler ro'yxatida crm:login borligini tekshirish
php artisan schedule:list | grep crm

# cron ishlab turganini tekshirish (serverda schedule:run har daqiqada chaqirilishi kerak)
crontab -l
```
