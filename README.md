# Jocko

Shop management for a car-parts retailer: stock across warehouses, purchases, orders and payments, reports and low-stock alerts. The interface is Persian (Jalali dates, Persian digits); admins sign in with an SMS code sent through [Kavenegar](https://kavenegar.com).

Laravel 13, Livewire 4, Flux, split into [nwidart modules](https://laravelmodules.com) under `Modules/`:

| Module | Covers |
| --- | --- |
| Auth | OTP login |
| Car | cars, car companies, colors |
| Dashboard | overview charts, categories, countries/provinces/cities, shared UI components |
| Inventory | items, brands, units, warehouses and their stock, suppliers, purchases, stock movements, low stock |
| Order | orders, invoices, payments |
| User | users, their cars, settings |

## Setup

Needs PHP 8.3+ (with `curl` and `fileinfo`), Composer, Node 22 and a Redis server (for the queue). The database is SQLite by default.

```bash
composer setup        # install, copy .env, key:generate, migrate, npm install, build
php artisan module:seed Dashboard Car User Inventory Order   # optional demo data, in this order
composer dev          # server, Vite, log tail and Horizon
```

`composer dev` starts Horizon, which runs the queue on Redis. `.env.example` uses the `predis` client, so no PHP Redis extension is needed; set `REDIS_HOST` and `REDIS_PORT` if your server is not on `127.0.0.1:6379`.

## Queue

The low-stock text (sent when an item first drops to its minimum) goes through the queue, so Horizon must be running or it is never sent. `composer dev` starts it; in production run `php artisan horizon` under a process manager and `php artisan horizon:terminate` on every deploy so it restarts with the new code. The login code is not queued, because the user is waiting for it.

The Horizon dashboard is at `/horizon`. It is open when `APP_ENV=local`; elsewhere the `viewHorizon` gate in `app/Providers/HorizonServiceProvider.php` decides who sees it.

To run without Redis, set `QUEUE_CONNECTION=database` and use `php artisan queue:work` instead of Horizon.

## Signing in

Only users of type `admin` can sign in, with their mobile number and a 6-digit code sent by SMS. Set `KAVENEGAR_API_KEY`, `KAVENEGAR_SENDER` and `KAVENEGAR_OTP_TEMPLATE` in `.env` to send real messages.

For local work, set `SMS_ENABLED=false`: nothing is sent and the message, code included, is written to `storage/logs/laravel.log` (`php artisan pail` follows it). This is ignored in production. The seeded users have random mobile numbers; get an admin's with:

```bash
php artisan tinker --execute="echo Modules\User\Models\User::admin()->value('mobile');"
```

## Jocko Control

The shop can report to the central Jocko Control server (online status, version, remote commands). It is optional: with nothing configured the shop runs standalone and the module does nothing.

```dotenv
JOCKO_CONTROL_URL=https://control.example.com
JOCKO_CONTROL_TOKEN=...   # shown once when the device is registered in Jocko Control
```

The token is a secret: keep it in `.env` only and never commit it. In production the URL must be `https://`.

```bash
php artisan control:status      # check the connection and show how this device is registered
php artisan control:heartbeat   # what the scheduler runs every minute
php artisan control:poll        # run queued remote commands now
php artisan control:push        # send recorded changes now (the scheduler does this every minute)
php artisan control:resync      # queue a fresh full copy of the data (optionally: control:resync order payment)
```

The scheduler must be running (`composer dev` starts it; in production use cron with `schedule:run`). Remote commands are limited to a fixed allowlist: `check_version`, `collect_diagnostics`, `refresh_config` and `sync_now` (push the outbox immediately). An unreachable server is normal and only logged once per outage.

### Data sent to Jocko Control

Once linked, every create, update, delete and restore of the models listed in `config/control.php` (`sync.entities`) is recorded in the `control_outbox_events` table, in the same database transaction as the change, and pushed to Jocko Control in order. This is what lets administrators browse a read-only copy of the shop there. Run `php artisan migrate` after updating.

- **Offline-safe:** events wait in the outbox while the internet is down and are sent when it returns. Delivery is repeatable; Jocko Control ignores events it already has. A shop that is not linked records nothing.
- **First link:** the first push also queues the current state of every existing record, so nothing created earlier is missing. `control:resync` repeats that on demand if you ever suspect a gap.
- **Allowlist:** `sync.entities` lists, per model, the only fields that leave the machine. Passwords, OTP codes, remember tokens, settings/API keys, roles and uploaded files are never sent. Do not add a credential to that list.
- Confirmed events are kept 7 days (`sync.retention_days`) and then pruned; events refused for good by Jocko Control stay in the table with the reason in `rejected_code` / `last_error`. `control:status` shows how many events are waiting.

## Language

`APP_LOCALE=fa` is the default. English strings live in `resources/lang/en` and `Modules/*/resources/lang/en`; set `APP_LOCALE=en` to use them.

## Tests and checks

```bash
composer test             # Pint, PHPStan (level 8), then the Pest suite
composer lint             # fix code style
npx playwright install chromium   # once
composer test:browser     # real-browser smoke tests of the list pages
```

CI (`.github/workflows/tests.yml`) runs the same checks, then the browser tests.
