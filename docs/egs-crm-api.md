# EGS CRM API (`crm:ping`, `crm:daily-import`, `crm:import-custom-operations`, `crm:plate-operators`, `scrape:zanjeer-operators`)

CRM bilan faqat integratsiya API'si orqali ishlanadi (`app/Services/EgsCrmClient.php`).
Selenium / headless Chrome endi ishlatilmaydi.

## Sozlash

`.env`:

```
EGS_CRM_API_URL=https://crm.egsgroupapp.uz/api/v1/integration
EGS_CRM_API_KEY=<kalit>
```

Tekshirish: `php artisan crm:ping` (Boshqaruv → "CRM API tekshiruvi") — kalit
to'g'ri bo'lsa granitsalar ro'yxati (ID, nomi) chiqadi; noto'g'ri bo'lsa 401.

API daqiqasiga 120 so'rov qabul qiladi — klient so'rovlar orasida 0.5 s kutadi,
429 kelsa `Retry-After` bo'yicha kutib qayta urinadi.

## Buyruqlar

| Buyruq | API | Nima qiladi |
|---|---|---|
| `crm:daily-import` (08:00) | `GET /borders`, `POST /custom-operations/import` | Kunlik fayllarni konvert qilib, granitsa nomi -> ID, "Торнадо"ga yuklaydi |
| `crm:import-custom-operations {path} --border=` | xuddi shu | Bitta faylni yuklaydi (granitsa fayl nomidan yoki `--border=` nom/ID) |
| `crm:plate-operators {file}` | `GET /orders?search=<raqam>` | Raqam bo'yicha eng ko'p qatnashgan OPERATION menejeri -> "Manager" ustuni |
| `scrape:zanjeer-operators {file}` | `GET /contragents?search=<kompaniya>` | Kompaniyaning mas'ul xodimi -> H ustuni (pipeline) |

Import fayli: `Avtomobil raqami` va `Sana` ustunlari. Bir faylni qayta yuklash
xavfsiz (yozuvlar yangilanadi). Xato bo'lsa fayl to'liq bekor qilinadi (422) —
xabar buyruq natijasida chiqadi.

Granitsa nomi CRM'dagi nomga mos bo'lishi kerak (tire turi va bo'sh joylar
farqi hisobga olinmaydi). Topilmasa xabarda mavjud granitsalar ro'yxati chiqadi.
