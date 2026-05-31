#!/usr/bin/env bash
# =============================================================================
#  CUK Concours — shared-hosting deploy script
#  MUST be run with bash, FROM the app root:   bash deploy/deploy.sh
#  (Do NOT use `sh deploy/deploy.sh` — this script uses bash-only features.)
#
#  It is SAFE to re-run.
#
#  Override the PHP / composer binaries if your host uses versioned names:
#     PHP_BIN=php8.4 COMPOSER_BIN="php8.4 /home/me/composer.phar" bash deploy/deploy.sh
# =============================================================================

# Many shared hosts open SSH with a stripped PATH (no dirname/head/mkdir/chmod).
# Restore the standard locations FIRST so coreutils + php/composer resolve.
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin:/opt/cpanel/composer/bin:${PATH:-}"

# Refuse to run under a non-bash shell (dash/busybox would choke on arrays).
if [ -z "${BASH_VERSION:-}" ]; then
    echo "ERROR: run with bash, not sh:   bash deploy/deploy.sh" >&2
    exit 1
fi

set -euo pipefail

say()  { printf '\n\033[1;34m> %s\033[0m\n' "$*"; }
ok()   { printf '  \033[1;32m[ok] %s\033[0m\n' "$*"; }
warn() { printf '  \033[1;33m[!] %s\033[0m\n' "$*"; }

# --- locate app root using PURE BASH (no external `dirname`) ------------------
# Preferred: we're already standing in the app root (artisan present).
if [ -f artisan ]; then
    APP_ROOT="$PWD"
else
    # Derive from the script's own path: strip "/deploy.sh" then "/deploy".
    _src="${BASH_SOURCE[0]}"
    _dir="${_src%/*}"                       # ".../deploy"  (or "deploy.sh" if no slash)
    if [ "${_dir}" = "${_src}" ]; then _dir="."; fi
    cd "${_dir}/.." 2>/dev/null || true     # deploy/ -> app root
    APP_ROOT="$PWD"
fi
cd "${APP_ROOT}"

if [ ! -f artisan ]; then
    warn "Could not find 'artisan' in ${APP_ROOT}."
    warn "Run this from the app root:   cd <app-folder> && bash deploy/deploy.sh"
    exit 1
fi

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

say "App root: ${APP_ROOT}"
# Print the PHP version without `head` (use PHP itself).
"${PHP_BIN}" -r 'echo "PHP ".PHP_VERSION." (".PHP_BINARY.")".PHP_EOL;' \
    || { warn "Cannot execute '${PHP_BIN}'. Set PHP_BIN to your PHP 8.4 CLI, e.g. PHP_BIN=php8.4 bash deploy/deploy.sh"; exit 1; }

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
# Use PHP to create the tree — works even if coreutils `mkdir` is absent from
# the host's jailed PATH.
"${PHP_BIN}" -r '
    foreach ([
        "storage/app/public","storage/app/private",
        "storage/framework/cache/data","storage/framework/sessions",
        "storage/framework/views","storage/logs","bootstrap/cache",
    ] as $d) { if (!is_dir($d)) { @mkdir($d, 0775, true); } }
    echo "storage tree present\n";
' || warn "could not create some storage dirs — check writability"
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
# Prefer coreutils chmod; fall back to a PHP recursive chmod if it's absent.
if command -v chmod >/dev/null 2>&1; then
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null \
        || warn "chmod partial — check writability of storage/ and bootstrap/cache/"
else
    "${PHP_BIN}" -r '
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(".", FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach (["storage","bootstrap/cache"] as $base) {
            @chmod($base, 0775);
            if (!is_dir($base)) continue;
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $p) { @chmod($p->getPathname(), $p->isDir() ? 0775 : 0664); }
        }
        echo "permissions set (php)\n";
    ' || warn "could not adjust permissions — check writability"
fi
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
