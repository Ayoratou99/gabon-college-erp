#!/usr/bin/env bash
# =============================================================================
#  CUK Concours — shared-hosting deploy script
#  Run AFTER `git pull`, from anywhere:   bash deploy/deploy.sh
#  (it cd's to the app root itself).
#
#  It is SAFE to re-run. It does NOT touch the database (you import the dump
#  separately, and run migrations only when you ship new ones — see end).
#
#  Override the PHP / composer binaries if your host uses versioned names:
#     PHP_BIN=php8.4 COMPOSER_BIN="php8.4 /home/me/composer.phar" bash deploy/deploy.sh
# =============================================================================
set -euo pipefail

# --- locate app root (parent of this script's dir) ---------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${APP_ROOT}"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

say()  { printf '\n\033[1;34m▶ %s\033[0m\n' "$*"; }
ok()   { printf '  \033[1;32m✓ %s\033[0m\n' "$*"; }
warn() { printf '  \033[1;33m! %s\033[0m\n' "$*"; }

say "App root: ${APP_ROOT}"
"${PHP_BIN}" -v | head -1

# --- 0. pre-flight (fails fast on missing extensions / wrong PHP) ------------
say "Pre-flight checks"
"${PHP_BIN}" deploy/preflight.php

# --- 1. .env must exist ------------------------------------------------------
if [ ! -f .env ]; then
    warn ".env missing — copy the template and fill it, then re-run:"
    warn "   cp .env.production.example .env && nano .env"
    exit 1
fi
ok ".env present"

# --- 2. composer dependencies (prod, optimized autoloader) -------------------
say "Installing PHP dependencies (no-dev)"
if command -v "${COMPOSER_BIN%% *}" >/dev/null 2>&1; then
    ${COMPOSER_BIN} install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    ok "composer install done"
else
    warn "composer not found — if vendor/ is already present (uploaded), continuing."
    warn "Otherwise install composer or upload vendor/ via SFTP."
fi

# --- 2b. database migrations -------------------------------------------------
#   `migrate --force` runs ONLY the migrations not yet recorded in the
#   `migrations` table, so it is a safe no-op right after importing the dump
#   (the dump already carries that table). On later releases it applies just
#   the new migrations.
#
#   First-ever deploy gotcha: if you run this script BEFORE importing the dump,
#   migrate would build an empty schema and the later dump import would then
#   clash. For that one case, skip it:  SKIP_MIGRATE=1 bash deploy/deploy.sh
say "Database migrations"
if [ "${SKIP_MIGRATE:-0}" = "1" ]; then
    warn "SKIP_MIGRATE=1 → skipping migrations (run the DB dump import, then re-run without the flag)"
else
    "${PHP_BIN}" artisan migrate --force
    ok "migrations up to date"
fi

# --- 3. storage dirs (git doesn't track them) + public symlink ---------------
say "Ensuring storage directories"
mkdir -p storage/app/public storage/app/private \
         storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
ok "storage tree present"

say "Linking storage"
if [ -L public/storage ] || [ -e public/storage ]; then
    ok "public/storage already present"
else
    # --relative keeps the symlink valid even if the absolute path changes.
    "${PHP_BIN}" artisan storage:link --relative \
        || warn "storage:link failed (symlinks may be disabled on this host). If uploaded files 404, ask support to enable symlink() or create public/storage manually."
fi

# --- 4. clear stale caches, then rebuild the SAFE ones ------------------------
#   config:cache → safe (no env() outside config/)
#   view:cache   → safe
#   event:cache  → safe
#   route:cache  → SKIPPED on purpose: the app has closure route actions, which
#                  cannot be serialized. Routes resolve fine without it.
say "Rebuilding caches"
"${PHP_BIN}" artisan optimize:clear || true
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan event:cache || true
ok "config + view + event caches built (route:cache intentionally skipped)"

# --- 5. writable dirs --------------------------------------------------------
say "Fixing permissions on storage/ and bootstrap/cache/"
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || warn "chmod partial — check writability of storage/ and bootstrap/cache/"
ok "permissions set"

# --- 6. done -----------------------------------------------------------------
say "Deploy finished."
cat <<'EOF'

  NOTES:
   • Migrations ran automatically (migrate --force). After the very first dump
     import they are a no-op; later releases apply only their new migrations.
   • FIRST deploy ordering: import the DB dump FIRST, then run this script.
     If you must run it before importing, use:  SKIP_MIGRATE=1 bash deploy/deploy.sh
   • If anything misbehaves after caching:      php artisan optimize:clear

EOF
