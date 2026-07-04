# GeoSeeder

Country + city reference data for seeding, installed as a **local path package** from `app/Vendor/GeoSeeder`.

Ships data for **Egypt (EG)**, **Kuwait (KW)**, **UAE (AE)**, and **KSA (SA)** — name (English + Arabic), ISO2/ISO3 codes, phone code, currency, flag emoji, timezone, and a list of major cities with coordinates.

This package holds **only data + read access to it** — no Eloquent models, no migrations, no artisan commands. The `Country` and `City` modules own the actual database tables and seeders; this package is what they seed *from*, so the reference data can grow (more countries, more cities) without touching module code.

## Install

```bash
composer require local/geo-seeder:"*"
```

## Usage

```php
use Local\GeoSeeder\GeoDataRepository;

$geo = app(GeoDataRepository::class);

$geo->supported();      // ['AE', 'EG', 'KW', 'SA']
$geo->has('EG');        // true
$geo->country('EG');    // ['name' => 'Egypt', 'iso2' => 'EG', ...]
$geo->cities('EG');     // [['name' => 'Cairo', 'latitude' => 30.0444, ...], ...]

$geo->country('FR');    // throws InvalidArgumentException — not shipped
```

## Which countries get seeded

`config/geo_seeder.php` → `countries` is the single source of truth, read by `App\Modules\Country\Database\Seeders\CountrySeeder`, `App\Modules\City\Database\Seeders\CitySeeder`, and the `php artisan geo:seed` command:

```bash
GEO_SEED_COUNTRIES=EG,KW,AE,SA   # .env, comma-separated ISO2 codes
```

Override per-run without touching `.env`:

```bash
php artisan geo:seed --countries=EG,KW
```

## Adding a country

Drop a new `src/Data/{ISO2}.php` returning the same `['country' => [...], 'cities' => [...]]` shape (see any existing file), then add its code to `GEO_SEED_COUNTRIES`. No other code changes needed.
