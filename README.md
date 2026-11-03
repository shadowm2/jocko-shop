# Jocko

Shop management for a car-parts retailer: stock across warehouses, purchases, orders and payments, reports and low-stock alerts. The interface is Persian (Jalali dates, Persian digits); admins sign in with an SMS code sent through [Kavenegar](https://kavenegar.com).

Laravel 13, Livewire 4, Flux, split into [nwidart modules](https://laravelmodules.com) under `Modules/`:

| Module | Covers |
| --- | --- |
| Auth | OTP login |
| Car | cars, car companies, colors |
| Control | optional link to the central [Jocko Control](../jocko-control) server: heartbeat, remote commands, change sync (see [Jocko Control](#jocko-control)) |
| Dashboard | overview charts, categories, countries/provinces/cities, shared UI components |
| Inventory | items, brands, units, warehouses and their stock, suppliers, purchases, stock movements, low stock |
| Order | orders, invoices, payments |
| User | users, their cars, settings |

## Setup

Needs PHP 8.3+ (with `curl`, `fileinfo` and `pdo_mysql`), Composer, Node 22, MySQL and a Redis server (for the queue). Create an empty database and set `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env` (`.env.example` selects MySQL and leaves them commented out) before running `composer setup`, which migrates.

```bash
composer setup        # install, copy .env, key:generate, migrate, storage:link, npm install, build
php artisan module:seed Dashboard Car User Inventory Order   # optional demo data, in this order
composer dev          # server, Vite, log tail and Horizon
```

`composer dev` starts Horizon, which runs the queue on Redis. `.env.example` uses the `predis` client, so no PHP Redis extension is needed; set `REDIS_HOST` and `REDIS_PORT` if your server is not on `127.0.0.1:6379`.

## Uploaded images

Images (item, brand and car photos) are stored on the `public` disk, in `storage/app/public`, and served through the `public/storage` symlink. `composer setup` creates it; on an existing checkout, or after moving the project to another path, run `php artisan storage:link` (add `--force` to replace a stale link). Without it every uploaded image is a 404. Image URLs are relative (`/storage/...`), so they work from whatever address the app is opened on.

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

Every shop is a standalone installation with its own database, and works without internet. It can optionally report to the central [Jocko Control](../jocko-control) server (online status, version, remote commands, and a read-only copy of its data for administrators). The shop only ever makes **outbound HTTPS requests**; Control never connects into the shop's network. With nothing configured the shop runs standalone and the module does nothing.

```
 shop (this app, local MySQL/SQLite)                      Jocko Control (central)
 ┌───────────────────────────────┐   heartbeat, poll      ┌──────────────────────┐
 │ models ─▶ control_outbox_events│ ──────────────────────▶│ /api/v1 device API   │
 │            (same transaction)  │   push events (HTTPS)  │ sync_events inbox    │
 └───────────────────────────────┘ ◀── remote commands ───│ shop_records mirror  │
                                                           └──────────────────────┘
```

```dotenv
JOCKO_CONTROL_URL=https://control.example.com
JOCKO_CONTROL_TOKEN=...   # shown once when the device is registered in Jocko Control
```

The token is a secret: keep it in `.env` only and never commit it. In production the URL must be `https://`. To try both apps together on one machine, follow [Running Control and a shop together](../jocko-control#running-control-and-a-shop-together) in Control's README.

```bash
php artisan control:status      # check the connection and show how this device is registered
php artisan control:heartbeat   # report and run queued remote commands (the scheduler does this every 15 seconds)
php artisan control:poll        # run queued remote commands now
php artisan control:push        # send recorded changes now (or use Settings → Queued changes)
php artisan control:resync      # queue a fresh full copy of the data (optionally: control:resync order payment)
```

The scheduler must be running (`composer dev` starts it; in production use cron with `schedule:run` every minute, which also runs the sub-minute tasks). It heartbeats every 15 seconds, which is also how quickly a queued remote command is noticed; the heartbeat also reports how many changes are waiting and how long the oldest has waited, which Control shows under *Needs attention* when a shop cannot get through; changes are pushed within about 5 seconds of being recorded (only when something is waiting), and a full push runs every minute as a safety net. Remote commands are limited to a fixed allowlist: `check_version`, `collect_diagnostics`, `refresh_config` and `sync_now` (push the outbox immediately). An unreachable server is normal and only logged once per outage.

### Data sent to Jocko Control

Once linked, every create, update, delete and restore of the models listed in `config/control.php` (`sync.entities`) is recorded in the `control_outbox_events` table, in the same database transaction as the change, and pushed to Jocko Control in order. This is what lets administrators browse a read-only copy of the shop there. Run `php artisan migrate` after updating.

- **Offline-safe:** events wait in the outbox while the internet is down and are sent when it returns. Delivery is repeatable; Jocko Control ignores events it already has. A shop that is not linked records nothing.
- **First link:** the first push also queues the current state of every existing record, so nothing created earlier is missing. `control:resync` repeats that on demand if you ever suspect a gap.
- **Pictures:** the photos of items, brands and cars (the `media` model) are announced like any record, and Jocko Control also gets a **downsized preview** of each (longest side 800 px, JPEG; PNGs with transparency stay PNG), sent just before its event. The original never leaves this machine. Previews are made with PHP's GD extension (needs `gd`; without it pictures are listed without a preview) and skipped for a file that is missing or too big to decode with the available `memory_limit`. A first sync of a big catalogue is spread over several pushes (`sync` batches are cut at `files.max_per_batch` uploads), so the heartbeat is never held up. After updating from a version without this, run `php artisan control:resync media` once to send the pictures that already exist.
- **Allowlist:** `sync.entities` lists, per model, the only fields that leave the machine. Passwords, OTP codes, remember tokens, settings/API keys and roles are never sent, and neither are the original uploaded files (see below). Do not add a credential to that list.
- Confirmed events are kept 7 days (`sync.retention_days`) and then pruned; events refused for good by Jocko Control stay in the table with the reason in `rejected_code` / `last_error`. `control:status` shows how many events are waiting.

## Taking payments

Open an order's **Collect payment** (the banknotes button in the orders list) to take what it still owes, online or on a card terminal. Every payment is an *attempt* that only becomes a payment record once it is confirmed, and only once.

### Online gateways

| Gateway | Settings (System settings, stored encrypted) | Checked against |
| --- | --- | --- |
| Zarinpal | merchant ID (`sandbox` = 1 for testing; any UUID works as a sandbox merchant) | the official docs, and live against their sandbox |
| NextPay | API key | the official docs and sample code |
| Zibal | merchant (`zibal` is their test merchant and needs no account) | their published request sample and live against the test merchant; the verify call follows community clients and was confirmed live for an unpaid payment only |

A gateway appears on the payment screen once its credentials are set. Set the amount unit (toman or rial) and, if customers reach the shop on another address than `APP_URL`, the **payment return address**; gateways only accept the domain registered with them.

How it works: the shop asks the gateway for a payment and shows the customer's payment page (open it on their phone or copy the link). The customer's browser is sent back to `/payments/online/{id}/return`, and the shop then asks the gateway itself whether it was paid, so a forged return changes nothing. The shop also asks about every open payment each minute (`payments:reconcile`, run by the scheduler), so a customer who paid and never came back to the shop is still found, and a late payment on a given-up attempt is still recorded. Unpaid attempts expire after `PAYMENTS_ATTEMPT_TTL_MINUTES` (30). Gateway credentials never leave the shop and are not sent to Jocko Control; the resulting payments are synchronized like any other.

IDPay and Pay.ir are not included: their documentation could not be read when this was written (IDPay's site has an expired certificate), and a payment integration is not something to write from memory. Adding one is a class in `Modules/Order/Payments` implementing `PaymentGateway`, listed in `PaymentDrivers`.

### Card terminals

The **Card terminal** option works with any POS: enter the amount on the terminal, and when it approves, type the reference number it printed (and optionally the last four card digits). The payment is recorded as *Card terminal (POS)*.

Driving a terminal from the shop (sending it the amount and reading the result) needs the bank's own protocol, which is private to each bank and needs their documentation or SDK and a test terminal. The extension point is ready: implement `PosTerminal` (`isAutomatic()`, `start()`, `check()`), list it in `PaymentDrivers`, and the cashier screen and the reconcile command handle the rest.

## Warehouses

**Settings → General → Warehouse settings** decides whether the shop works with several warehouses. With the switch **off**, everything uses the *default warehouse*: there are no warehouse pickers on orders, purchases, stock adjustments or stock items, no transfers, and no warehouse columns or filters. The *Warehouses* menu is replaced by a *Stock* menu (items and low stock) once the default exists, and stays until then so a first warehouse can be created. Existing records keep the warehouse they were made in. A shop that has never chosen keeps working as before when it has several warehouses, and uses its only one when it has one.

## Queued changes

**Settings → General → Queued changes** lists the changes recorded for Jocko Control: waiting, refused (with the reason) and sent in the last 7 days. *Send now* pushes the queue right away; *Retry refused* gives the refused ones another chance. If Control cannot be reached, the page says so and the changes simply keep waiting.

## Language

`APP_LOCALE=fa` is the default. English strings live in `resources/lang/en` and `Modules/*/resources/lang/en`; set `APP_LOCALE=en` to use them.

## Tests and checks

```bash
composer test             # Pint, PHPStan (level 8), then the Pest suite
composer lint             # fix code style
npx playwright install chromium   # once
composer test:browser     # real-browser smoke tests of the list pages
```

The tests use an in-memory SQLite database (`phpunit.xml`), so PHP needs `pdo_sqlite` to run them. CI (`.github/workflows/tests.yml`) runs the same checks, then the browser tests, and runs the suite a second time on MySQL. The tests are written for both databases: a test that builds bare rows calls `deferForeignKeys()` (see `tests/Pest.php`), and no test may rely on a fixed id such as `item_id => 1`, because only SQLite starts counting again after every test. To run the suite on MySQL locally, point it at an empty database whose name ends in `_test`: `DB_CONNECTION=mysql DB_DATABASE=jocko_shop_test ./vendor/bin/pest` (the tests wipe it).

## License

Proprietary, all rights reserved. See [LICENSE](LICENSE).
