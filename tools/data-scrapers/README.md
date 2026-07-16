# Data Scrapers — Torap / CareerGuidance

Сбор данных вузов Казахстана для платформы абитуриентов.

## Статус

| Город | Вузы | ОП | С ценой | JSON |
|-------|------|-----|--------|------|
| Астана | 23 | 445 | 326 | `output/vipusknik_astana.json` |
| Алматы | 47 | 999 | 551 | `output/vipusknik_алматы.json` |

| Шаг | Статус |
|-----|--------|
| vipusknik scrape | ✅ Астана + Алматы |
| Laravel import | ✅ `data:import-scraped` |
| Дедуп | ✅ `data:dedupe-institutions` |
| Official top-6 | 🟨 scaffold |

## Быстрый пайплайн

```bash
cd data-scrapers
pip install -r requirements.txt

# Астана
python spiders/vipusknik_city_spider.py --city 5 --city-name Астана --out output/vipusknik_astana.json
python spiders/fix_vip_enrich.py

# Алматы
python spiders/vipusknik_city_spider.py --city 3 --city-name Алматы --out output/vipusknik_almaty.json

# Импорт
cd ../CareerGuidance-Laravel
php artisan data:import-scraped "../data-scrapers/output/vipusknik_astana.json" --update
php artisan data:import-scraped "../data-scrapers/output/vipusknik_almaty.json" --update
php artisan data:dedupe-institutions
```

## Схема БД

```
institutions  ← карточка вуза (name, address, lat/lng, website, …)
specializations ← образовательная программа
institution_specialties ← pivot: cost (₸/год), duration (лет)
     ↑
global_specialties → qualifications → specializations
```

## Что парсится с vipusknik

- список вузов Астаны (`?city_name=5`)
- на карточке: имя, email, phone, logo, description
- блок `.institution-specialty-list`:
  - группа (6В0xx)
  - название ОП
  - **стоимость за год**
  - **срок обучения**
- coords/website подмешиваются из `data/astana_universities.json`

## Топ-6 официальных сайтов (B)

```bash
python spiders/official_top6_spider.py
```

Сохраняет HTML для ручной/следующей настройки парсеров:

1. ЕНУ — enu.kz  
2. NU — nu.edu.kz  
3. КазАТИУ — kazatu.edu.kz  
4. МУА — amu.edu.kz / admission.amu.kz  
5. MNU — mnu.kz  
6. AITU — astanait.edu.kz  

## Реестр

[`data/astana_universities.json`](data/astana_universities.json) — 20+ вузов с контактами (egov + seed).

## Важно

- Не парсить Google Maps → Nominatim / Geocoding API  
- Rate-limit: ~1 req/s  
- Цены с агрегаторов — ориентир; для «официальности» сверять PDF приёмных  
- Источник и дата: поля `source`, `scraped_at` в JSON  
