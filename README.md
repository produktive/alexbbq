# Maverick.bbq

A self-hosted BBQ temperature dashboard for the [Maverick ET-732](https://www.maverickthermometers.com/) wireless thermometer. The app runs on a Raspberry Pi, reads probe temperatures over GPIO, and presents live charts, cook history, and push alerts in the browser.

Built with **Laravel 13**, **Livewire 4**, **Filament 5**, and **Livewire Flux**.

---

## Table of contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Quick setup](#quick-setup)
  - [Local development (Laravel Herd)](#local-development-laravel-herd)
  - [Create an admin user](#create-an-admin-user)
  - [Push notifications (optional)](#push-notifications-optional)
- [Configuration](#configuration)
- [Development](#development)
- [Production (Raspberry Pi)](#production-raspberry-pi)
- [Artisan commands](#artisan-commands)
- [Testing](#testing)
- [License](#license)

---

## Overview

Maverick.bbq intercepts transmissions from a Maverick ET-732 BBQ thermometer using a radio frequency chip connected to a Raspberry Pi via GPIO. A small C daemon (`maverick`) decodes the wireless signal and writes 
temperature readings to SQLite. The Laravel app displays those readings in real time, stores completed cooks, and can notify you when probe temperatures drift outside your thresholds. The home page shows the active 
cook (or the most recent finished cook) with an interactive temperature chart. Authenticated users can start and stop cooks, manage smokers, configure alerts, and edit past cook details.

---

## Features

### Live monitoring

- **Real-time temperature chart** — Food and BBQ probe readings plotted over time with Chart.js.
- **WebSocket updates** — Live cooks refresh automatically via [Laravel Reverb](https://laravel.com/docs/reverb) and Laravel Echo.
- **Live cook timer** — Elapsed time since the first reading for in-progress cooks.
- **Start / stop recording** — Start a new cook from the dashboard; stopping finalizes the cook and ends probe recording.
- **Cook Simulator** – Simulate cooking with realistic generated probe readings for testing on your computer.

### Cook management

- **Cook history** — Browse finished cooks with date, title, and description preview.
- **Cook descriptions** — Write detailed notes with stylized text, lists, links, and image gallery attached to each cook.
- **Individual cook pages** — Share the full chart, duration, and description for any finished cook.
- **Smoker tracking** — Associate cooks with named smokers; archive or restore smokers as needed.

### Interactive charts

- **Finished cook editing** — Delete individual readings, ranges of points, or add notes to specific timestamps (owner only).
- **Desktop & mobile accessible** — Edit chart points on any device.
- **Touch-friendly selection** — Drag to select a range of points on mobile.

### Alerts & notifications

- **Temperature thresholds** — Set min/max ranges for food and BBQ probes.
- **Alert frequency** — Configurable cooldown (1, 3, 5, 10, or 15 minutes) to avoid notification spam.
- **Web push notifications** — Browser push alerts when temperatures go out of range during a live cook.

### Authentication & security

- **Email + password login** — Laravel Fortify.
- **Passkeys (WebAuthn)** — Passwordless sign-in support.
- **Password reset** — Standard forgot-password flow.
- **Profile & appearance settings** — Update account details and theme preferences.

### Progressive Web App

- Installable as a standalone app for quick access from phone, tablet or desktop while tending the smoker.

### Statistics

- **Total cook time** — Aggregate duration across all recorded cooks (`/stats`).

---

## Architecture

```
┌─────────────────┐ 433MHz RF chip ┌──────────────────┐
│ Maverick ET-732 │ ─────────────► │     maverick     │  (C daemon, pigpio)
│   transmitter   │                └────────┬─────────┘
└─────────────────┘                         │ writes readings
                                            ▼
                                   ┌──────────────────┐
                                   │  SQLite database │
                                   └────────┬─────────┘
                                            │
                                            ▼
                                   ┌──────────────────┐
                                   │  Laravel app     │  Livewire + Filament UI
                                   └────────┬─────────┘
                                            │ WebSockets
                                            ▼
                                   ┌──────────────────┐
                                   │  Laravel Reverb  │  live chart updates
                                   └────────┬─────────┘
                                            │
                                            ▼
                                   ┌──────────────────┐
                                   │  Browser / PWA   │
                                   └──────────────────┘
```

| Component | Role |
|-----------|------|
| `maverick.c` | Reads GPIO pin 15, decodes ET-732 RF packets, inserts readings via Artisan |
| `maverick.sh` | Start/stop/status wrapper for the daemon (uses `sudo` on the Pi) |
| `maverick-fake.sh` | Local dev substitute — runs `php artisan maverick:simulate` instead of hardware |
| `MaverickService` | PHP interface to start/stop the daemon and finalize active cooks |
| `LiveCookBroadcast` | Pushes chart update events over Reverb |
| `TemperatureAlertService` | Evaluates probe readings and sends web push notifications |

---

## Requirements

### Application

| Dependency | Version |
|------------|---------|
| PHP | ^8.3 |
| Composer | 2.x |
| Node.js | 18+ (for Vite asset build) |
| SQLite | 3.x (default database) |

### Local development

- [Laravel Herd](https://herd.laravel.com/) (recommended) or any PHP 8.3+ environment with a web server

### Production (Raspberry Pi)

- Raspberry Pi with GPIO access (BCM pin 15)
- [pigpio](http://abyz.me.uk/rpi/pigpio/) library (to build `maverick`)
- PHP 8.4-FPM, Caddy (or similar reverse proxy)
- Systemd services for Reverb and the Maverick daemon
- Optional: Cloudflare DNS for TLS and DDNS (`cloudflare-ddns.sh`)

---

## Installation

### Quick setup

From the project root:

```bash
composer setup
```

This runs, in order:

1. `composer install`
2. Copies `.env.example` → `.env` (if missing)
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `npm install`
6. `npm run build`

Then start the development environment:

```bash
composer dev
```

This launches three processes concurrently:

- `php artisan serve` — web server at `http://localhost:8000`
- `php artisan pail` — log tail
- `npm run dev` — Vite hot reload

### Local development (Laravel Herd)

1. Clone the repository into your Herd sites directory (e.g. `~/Herd/maverickbbq`).
2. Run `composer setup`.
3. Open `https://alexbbq.test` (or your Herd domain).
4. In local mode (`APP_ENV=local`), the app automatically uses `maverick-fake.sh`, which simulates probe readings — no Raspberry Pi required.

**Reverb (live WebSocket updates):**

```bash
php artisan reverb:start --port=8080
```

Add to `.env`:

```env
REVERB_HOST=maverick.test
REVERB_PORT=8080
REVERB_SCHEME=https
```

Reverb connection settings are injected at runtime (no `VITE_REVERB_*` build variables needed).

### Create an admin user

Seed a default account:

```bash
php artisan db:seed
```

| Field | Value |
|-------|-------|
| Email | `admin@admin.com` |
| Password | `password` |

Change the password after first login. Registration is disabled — this is intended as a single-user or small household deployment.

### Push notifications (optional)

Generate VAPID keys and add them to `.env`:

```bash
php artisan webpush:vapid
```

Copy the generated keys into:

```env
VAPID_SUBJECT="${APP_URL}"
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

Enable push notifications from **Alerts** in the app after signing in.

---

## Configuration

Key environment variables (see `.env.example` for the full list):

| Variable | Description |
|----------|-------------|
| `APP_URL` | Public URL of the app (required for passkeys and push) |
| `APP_TIMEZONE` | Display timezone for cook timestamps (default: `America/New_York`) |
| `DB_CONNECTION` | Database driver (default: `sqlite`) |
| `BROADCAST_CONNECTION` | Set to `reverb` for live updates |
| `REVERB_*` | WebSocket server host, port, and credentials |
| `VAPID_*` | Web push notification keys |
| `MAVERICK_SCRIPT` | Override path to start/stop script |
| `MAVERICK_USE_SUDO` | Whether `maverick.sh` runs via `sudo` (default: `true` on Pi, `false` for fake) |
| `MAVERICK_FAKE_INTERVAL` | Seconds between simulated readings (default: `12`) |
| `MAVERICK_FAKE_BBQ_TARGET` | Simulated pit temperature target °F (default: `225`) |
| `CLOUDFLARE_*` | Used by `cloudflare-ddns.sh` for dynamic DNS on the Pi |

---

## Development

### Fake Maverick simulator

In local development, probe readings are generated automatically when a cook is active:

```bash
php artisan maverick:simulate
```

Or via the wrapper script:

```bash
./maverick-fake.sh start   # background
./maverick-fake.sh status
./maverick-fake.sh stop
```

### Linting

```bash
composer lint        # fix style with Laravel Pint
composer lint:check  # check only
```

### Building assets

```bash
npm run dev    # development with HMR
npm run build  # production build
```

---

## Production (Raspberry Pi)

### 1. Build the Maverick daemon

On the Pi, compile the C binary (requires [pigpio](https://abyz.me.uk/rpi/pigpio/download.html)):

```bash
gcc -o maverick maverick.c -lpigpio -lrt -pthread
chmod +x maverick maverick.sh maverick-artisan.sh
```

Ensure PHP-FPM can start the daemon via passwordless sudo for `maverick.sh`.

### 2. Deploy the Laravel app

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env   # configure for production
php artisan key:generate
php artisan migrate --force
npm ci && npm run build
php artisan config:cache
```

Set production values in `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true
MAVERICK_SCRIPT=/var/www/alexbbq/maverick.sh
MAVERICK_USE_SUDO=true
```

### 3. Install Reverb as a systemd service

```bash
sudo ./deploy/install-reverb-service.sh
```

Verify with:

```bash
./deploy/verify-reverb.sh
```

### 4. Configure Caddy

Copy and adapt the example Caddyfile:

```bash
sudo cp deploy/caddy/Caddyfile.example /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

The example includes Reverb WebSocket proxying and optional Cloudflare DNS TLS.

### 5. Dynamic DNS (optional)

Add a cron job to keep your Cloudflare DNS record updated:

```cron
*/5 * * * * /var/www/alexbbq/cloudflare-ddns.sh
```

---

## Artisan commands

| Command | Description |
|---------|-------------|
| `maverick:simulate` | Generate fake probe readings (local dev) |
| `app:import-legacy-data` | Import data from Alex.bbq v1 SQLite database |
| `cook:sync-ended-at` | Backfill `ended_at` timestamps from reading data |
| `cook:evaluate-alerts` | Manually evaluate temperature alerts for a reading |
| `alerts:diagnose` | Check alert configuration and VAPID setup |
| `app:normalize-cook-descriptions` | Convert legacy cook descriptions to RichEditor HTML |
| `app:normalize-legacy-reading-times` | Convert legacy local timestamps to UTC |
| `app:prune-cook-description-images` | Remove orphaned cook description image files |
| `webpush:vapid` | Generate VAPID keys for push notifications |

---

## Testing

```bash
composer test
```

This clears config cache, runs Pint in check mode, and executes the Pest test suite.

CI runs lint and test workflows on push (see `.github/workflows/`).

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
