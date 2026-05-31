# WAMP setup for CUK Concours

Five-minute checklist to serve the app through WAMP's bundled Apache.

## 1. Required PHP extensions

WAMP ships with everything we need by default, just turn them on (left-click WAMP icon → PHP → PHP extensions):

- `pdo_pgsql` *(critical — talks to the Docker postgres)*
- `pgsql`
- `intl`
- `bcmath`
- `gd`
- `mbstring`
- `zip`
- `fileinfo`
- `openssl`
- `curl`
- `sodium`

After toggling, restart Apache from the tray.

## 2. Apache vhost

Append [`cuk.test.conf`](cuk.test.conf) into `httpd-vhosts.conf` (the exact file is something like `C:\wamp64\bin\apache\apache2.4.62.1\conf\extra\httpd-vhosts.conf` — the version path varies).

Make sure `httpd.conf` includes vhosts:

```apache
# Towards the bottom of httpd.conf, leave uncommented:
Include conf/extra/httpd-vhosts.conf
```

## 3. Hosts file

Edit `C:\Windows\System32\drivers\etc\hosts` (as administrator) and add:

```
127.0.0.1   cuk.test
```

## 4. Restart Apache

WAMP tray icon → "Restart Services" — both Apache and PHP-FPM pick up the new vhost and php-extension changes at the same time.

## 5. Smoke test

Open <http://cuk.test/> in a browser. You should land on the public welcome page.

If you see a 403, check the `Require local` line in `cuk.test.conf` — your browser must be coming from `127.0.0.1`. If you're testing from a different machine on the LAN, change it to `Require all granted`.

## Troubleshooting

| Symptom                                                       | Fix                                                                                                          |
|---------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| "could not find driver" on artisan migrate                    | Enable `pdo_pgsql` + `pgsql` in WAMP PHP extensions, then restart Apache.                                    |
| 404 on every page after the homepage                          | Confirm `AllowOverride All` is set on the `<Directory>` block — Laravel's `public/.htaccess` needs it.       |
| Static files (CSS / JS) return 404                            | Run `npm run build` once; the manifest lives at `public/build/manifest.json`.                                |
| "Connection refused" to Postgres                              | `docker compose up -d` — the cuk-postgres container has to be running.                                       |
| Page renders blank with a 500 logged                          | Open `storage/logs/laravel.log` — that's where every error and every outgoing email lands in dev.            |
