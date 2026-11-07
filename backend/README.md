# Market Scan API

Laravel 11 (PHP 8.3) on its own MySQL 8 database. Plan: [`BUILD.md`](BUILD.md). Contract: [`../docs/openapi.yaml`](../docs/openapi.yaml).

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve   # http://localhost:8000
php artisan queue:work                            # AI reading, pushes to abraa.com and GHL
php artisan test                                  # SQLite in memory, no outside calls
```
