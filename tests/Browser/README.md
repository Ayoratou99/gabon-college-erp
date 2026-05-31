# Dusk browser tests

Laravel Dusk runs Chrome (or Chromium / Edge / Brave) against a live server.
On this project Postgres lives in Docker; everything else — Apache, PHP,
Node, Dusk itself — runs on the WAMP host.

## One-time setup

### 1. Install the matching ChromeDriver

Dusk includes an artisan command to fetch it (`php artisan dusk:chrome-driver`),
but on Windows it often fails with **SSL certificate problem: unable to get
local issuer certificate** because the host PHP has no CA bundle configured.

Easiest fix — download manually:

1. Check your installed Chrome version (`chrome://version` in the address bar, or
   ```powershell
   (Get-Item 'C:/Program Files/Google/Chrome/Application/chrome.exe').VersionInfo.ProductVersion
   ```
   ).

2. Open <https://googlechromelabs.github.io/chrome-for-testing/> and grab the
   "Stable" — or the matching milestone — ChromeDriver `win64` zip.
   For Chrome 148, the direct link is:
   <https://storage.googleapis.com/chrome-for-testing-public/148.0.7778.178/win64/chromedriver-win64.zip>

3. Unzip and copy `chromedriver.exe` to:
   ```
   cuk-app/vendor/laravel/dusk/bin/chromedriver-win.exe
   ```
   (note the **chromedriver-win.exe** name Dusk expects — not the plain
   `chromedriver.exe` from the zip).

The whole thing is ~10 MB. If you'd rather have Dusk do it for you, configure
PHP's CA file once:

```ini
; php.ini (the one WAMP / CLI uses — `php --ini` shows you which)
curl.cainfo  = "C:\path\to\cacert.pem"
openssl.cafile = "C:\path\to\cacert.pem"
```

(Download `cacert.pem` from <https://curl.se/ca/cacert.pem>.) Then:
`make chromedriver` (or `php artisan dusk:chrome-driver --detect`).

### 2. Prepare the test database

```bash
make dusk-prep
```

That creates the `cuk_dusk` database in the Postgres container, migrates it,
and runs every seeder so the tests can rely on the catalog of nationalités,
sections, etc. It also writes a test admin (`telephone=060000000` /
`password=admin1234`) into that DB only.

`.env.dusk.local` (already at the repo root) tells Laravel:

* DB = `cuk_dusk` (separate from your dev `cuk` database — Dusk never touches your dev data)
* Cache / sessions = file (no Redis)
* Mail = `log` (no SMTP)
* 2FA disabled so admin tests don't have to scan a QR

### 3. Run

```bash
make dusk
```

This boots `php artisan serve` on `127.0.0.1:8000`, fires Chrome via
ChromeDriver, runs every test under `tests/Browser/`, and tears the server
down at the end.

## What's covered

| File                                                                 | What it tests                                                                                |
|----------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| [`PublicPagesTest.php`](PublicPagesTest.php)                         | Home page (hero, navbar, footer, brand logo from `/img/cuk/`), `/verifier-demande`, `/resultats`, login page (uses the public theme), first-login wizard step 1. |
| [`AdminFlowTest.php`](AdminFlowTest.php)                             | Login as the seeded super-admin → dashboard with `.session-band` + sidebar. Sessions, users, parametrage, planning all render. User detail page shows the 2FA + password reset buttons. Candidat list shows the new filter selects (centre, section, série, deja_bac, sexe). |

## When to refresh the test DB

Whenever you add a migration or change a seeder:

```bash
make dusk-fresh    # drops cuk_dusk, recreates, migrates, reseeds
```

## Debugging a failing test

* Screenshots of failures land in [`tests/Browser/screenshots/`](screenshots/).
* Console logs land in [`tests/Browser/console/`](console/).
* HTML snapshots (with `dump-html` enabled) land in [`tests/Browser/source/`](source/).
* Pass `--browse` to keep Chrome visible:
  ```bash
  php artisan dusk --browse
  ```
  (Drop `--headless=new` from `DuskTestCase::driver()` if you want this to be
  the default.)

## What we deferred

Coming in a focused future round (so you don't get half-broken features):

* A multi-step inscription Dusk test (registration → upload documents → review).
* Recovery flow end-to-end (register → admin rejects → candidat modifies →
  goes back to "en cours").
* eBilling callback simulation (no real HTTP).
* Mobile-viewport responsive assertions (`$browser->resize(375, 667)` per Bootstrap breakpoint).
