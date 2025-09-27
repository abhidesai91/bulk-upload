# Bulk CSV Import + Chunked Image Upload (Laravel 10)

This project provides:
- Products CSV import with upsert by SKU, duplicate-in-file detection, invalid row handling, and per-import summary
- Background import via Job, with per-row logs and a live-updating status page
- Drag-and-drop image upload with chunked/resumable uploads and SHA-256 checksum validation
- Image variant generation at 256px, 512px, 1024px using Intervention Image v3

## Tech Stack
- PHP 8.2+, Laravel 10
- Intervention Image v3 + Laravel bridge
- League CSV
- Bootstrap 5 (basic UI)

## Requirements
- PHP extension: GD or Imagick for image processing (at least one)
- Database (MySQL/MariaDB/SQLite)
- Composer

## Quick Start

1) Install dependencies
```
composer install
```

2) Configure environment
```
cp .env.example .env
php artisan key:generate
```
Edit `.env` to configure the database.

3) Run migrations
```
php artisan migrate
```

4) Link storage (only needed if you want web-accessible files from `public/storage`)
```
php artisan storage:link
```

5) Queue configuration (recommended)
By default, Laravel queues run in `sync` mode (jobs run inline). To process imports truly in the background:
```
# .env
QUEUE_CONNECTION=database
```
Create queue tables and start a worker:
```
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
php artisan queue:work --queue=default --tries=3
```

6) Start the app
```
php artisan serve

```

## Useful Commands
```
php artisan products:csv --count=10000         # Generate large sample CSV
php artisan queue:work                         # Process queued jobs
```