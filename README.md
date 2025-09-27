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
# Or use your local Apache/Nginx
```

## Image Processing Driver
The app generates image variants if GD or Imagick is available. On Windows/XAMPP:
- Enable `extension=gd` (and/or `extension=imagick`) in `php.ini`
- Restart Apache
- Verify via `php -m` that `gd` appears

If no driver is available, uploads still succeed but variant paths remain `null`.

Backfill variants for existing images after enabling the driver:
```
php artisan images:regenerate --only-missing
# or overwrite all
php artisan images:regenerate --force
```

## Features and URLs

- CSV Import (landing page): `GET /`
  - Upload a CSV with header: `sku,name,price,image` (image column optional)
  - On submit, a `ProductImport` record is created and a `ProcessProductImport` job is dispatched
  - You are redirected to the status page: `GET /import/{id}`

- Import Status Page: `GET /import/{id}`
  - Shows `status` (pending/processing/completed/failed)
  - Live summary: total, imported, updated, invalid, duplicates
  - Recent row logs (latest 50) with row number, sku, and per-row status
  - Auto-refreshes every 5 seconds while processing

- Chunked Image Upload UI: `GET /upload`
  - Drag-and-drop multiple images
  - Client calculates SHA-256; server validates each chunk and the final file
  - Server stores original at `storage/app/uploads/images/{filename}`
  - Variants saved as `storage/app/uploads/images/{256|512|1024}_{filename}`
  - To serve publicly, switch to the `public` disk and run `storage:link`

## CSV Format Example
```
sku,name,price,image
SKU-001,Red Shirt,19.99,red-shirt.jpg
SKU-002,Blue Pants,29.50,blue-pants.jpg
SKU-001,Duplicate Row,20.00,
,MissingSku,10.00,
```
Behavior:
- Upsert by `sku` (SKU-001 updates or imports initially)
- Duplicate rows within the same file are counted as `duplicates`
- Rows missing `sku` or `name` are `invalid`

## Generating a Large CSV for Testing
Use the built-in command:
```
php artisan products:csv --count=10000
# Output: storage/app/products_10000.csv
```
Upload that file on the CSV import page.

## Where Files Are Stored
- Original images: `storage/app/uploads/images/{filename}`
- Variants: `storage/app/uploads/images/256_{filename}`, `512_{filename}`, `1024_{filename}`
- If you prefer public URLs, change saving to `Storage::disk('public')` and ensure `php artisan storage:link` has been run.

## Jobs and Status Tracking
- `product_imports` table stores per-import summary and lifecycle timestamps
- `product_import_rows` stores per-row results: imported, updated, invalid, duplicate
- CSV imports are enqueued; with `QUEUE_CONNECTION=database` they are processed by `queue:work`
- If the queue is `sync`, the controller uses `dispatchAfterResponse()` so the browser returns immediately and processing continues after response

## Tests
Run the full test suite:
```
php artisan test
```
Run specific tests:
```
php artisan test --filter=ProductImportJobTest
php artisan test --filter=ProductImportTest
php artisan test --filter=UploadChunkTest
```
Notes:
- `UploadChunkTest` requires a working image driver; if GD is not enabled in the test environment it auto-skips

## Troubleshooting
- Uploads succeed but variants are `null`
  - Ensure GD or Imagick is enabled and recognized by PHP (`php -m`)
  - Clear caches: `php artisan config:clear && php artisan cache:clear`
  - Backfill: `php artisan images:regenerate --only-missing`

- CSV import page waits until done
  - Use `QUEUE_CONNECTION=database` and run a worker with `php artisan queue:work`
  - Alternatively, `dispatchAfterResponse()` is used to return immediately, even on `sync`

- Permission issues when saving files
  - Ensure the web server user can write to `storage/app` (or `storage/app/public` if using the public disk)

## Security & Concurrency
- Chunks are written atomically and merged under a file lock
- Final checksum (SHA-256) is validated server-side
- Product upserts are idempotent by SKU
- Linking primary product image by filename is idempotent

## Useful Commands
```
php artisan images:regenerate --only-missing   # Backfill image variants
php artisan products:csv --count=10000         # Generate large sample CSV
php artisan queue:work                         # Process queued jobs
```