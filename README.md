# Floré – Web-Based Florist Management System

Floré is a web application for a single florist shop. Customers browse and order bouquets online and get help choosing one. The shop owner manages inventory, orders, payments, customers and reports from one admin portal.

It was built as the CPP400 final project (Asia e University, May 2026 semester) with **Laravel 12, MySQL and Bootstrap 5**.

> Release **1.0.0** – UAT 49/49 passed · 295 automated tests passing.
> Full documentation: [`docs/Flore_Final_Release_UAT_Report.docx`](docs/Flore_Final_Release_UAT_Report.docx)

## Features

| Customer portal | Admin portal |
|---|---|
| Register, login, password reset, profile | Live dashboard: revenue, active orders, wizard usage, stock alerts |
| Catalogue with search and filters, bouquet detail | Order management, payment-proof verification, status workflow |
| Flowers sold per stem with bundle pricing | Bouquet inventory, categories, stock levels, product photos |
| Cart and 4-step checkout with greeting card | Customer records and order history |
| Bank-transfer proof upload, order tracking, cancellation | Sales, best-seller and customer reports with CSV export |
| Testimonials after delivery | Testimonial moderation and Smart Reminder log |

**Smart Bouquet Recommendation Wizard** asks five questions: occasion, colour, style, size and budget. It scores every bouquet with a rule-based engine and explains why each one matches.

**Smart Reminder System** is a daily scheduled job. It emails customers before birthdays, anniversaries and other special moments they save.

## Requirements

- PHP 8.2 with the GD, fileinfo and pdo_mysql extensions (XAMPP 8.2 is fine)
- MySQL or MariaDB
- Composer 2
- Node.js 18+ and npm

## Installation

```bash
composer install
npm install && npm run build
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

Create an empty database named `flore_db`, then set these values in `.env`:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_DATABASE=flore_db
DB_USERNAME=root
DB_PASSWORD=
ADMIN_EMAIL=admin@flore.test
ADMIN_PASSWORD=            # at least 12 characters
PAYMENT_BANK=BCA
PAYMENT_ACCOUNT_NUMBER=
PAYMENT_ACCOUNT_NAME="Floré Florist"
```

Next, create the tables, seed the catalogue and start the app:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve                         # http://127.0.0.1:8000  ·  admin: /admin/login
```

The seeders create the admin account, 4 categories, 12 bouquets, 16 per-stem flowers and 19 recommendation rules. You can run them again safely: product details are updated, but stock that is already in use is never overwritten.

To load sample data for a presentation, run the command below. It creates 6 customers (password `demo12345`), 15 orders, testimonials and wizard history. It cannot run in production.

```bash
php artisan db:seed --class=DemoSeeder
```

### Product photos

Put photos in `foto-bouquet/` and name each file after its bouquet, for example `The Sweet Blush Romance.jpg`. Then run:

```bash
php artisan bouquets:import-photos            # add --dry-run to preview
```

Each photo is cropped to 4:3, colour-balanced, stamped with a small Floré logo and saved as WebP. Photos uploaded from the admin portal get the same processing.

### Smart Reminder scheduler

`reminders:send` runs every day at 08:00. On a server, add one cron entry:

```cron
* * * * * cd /path/to/flore && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, create a Task Scheduler task that runs `php artisan schedule:run` every minute. You can also start the job by hand from **Admin → Smart Reminder → Jalankan Sekarang**. With `MAIL_MAILER=log`, emails are written to `storage/logs/laravel.log`. To send real emails, set the SMTP values in `.env`.

## Testing

```bash
php artisan test
```

The suite runs on an in-memory SQLite database. It covers every functional requirement (FR-01 – FR-12), a security audit (CSRF, SQL injection, authorisation, private payment proofs), stock integrity, the recommendation engine, reminders and reports.

## Before going live

- `APP_ENV=production`, `APP_DEBUG=false`
- Set a strong `ADMIN_PASSWORD`, then run `php artisan db:seed --class=AdminSeeder`
- Enter real bank details and SMTP credentials
- Turn on the scheduler and set up regular database backups (`mysqldump`)
- Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`

## Project structure

```
app/Http/Controllers        customer and Admin controllers
app/Services                OrderService, OrderWorkflow, RecommendationEngine, ReminderPlanner, SalesAnalytics, ProductPhoto
app/Console/Commands        reminders:send, bouquets:import-photos
database/seeders            final catalogue + DemoSeeder
lang/id                     Indonesian messages
resources/views             Blade templates (customer + admin)
tests/Feature               PHPUnit feature tests
```

## Credits

Amanda Anastasya Aryanto (C3010930145), School of Science and Technology, Asia e University.
Academic facilitator: Ts. Dr. Husna Sarirah Husin.
Font: Playfair Display (SIL Open Font License).
