# ------------------------------------------------------------
# CUK Concours — bootstrap script for Windows / PowerShell.
# Run from the cuk-app/ directory:   .\scripts\setup.ps1
#
# Idempotent: re-running only fills in what's missing.
# ------------------------------------------------------------

# Composer / artisan often write progress to stderr even on success.
# `Continue` keeps native-cmd stderr from aborting the whole script;
# we check $LASTEXITCODE explicitly via Invoke-Native instead.
$ErrorActionPreference = "Continue"
$PSNativeCommandUseErrorActionPreference = $false

function Require-Cmd {
    param([string]$Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name is required but was not found in PATH."
    }
}

function Invoke-Native {
    param([string]$Description, [scriptblock]$Block)
    & $Block
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed (exit $LASTEXITCODE)"
    }
}

Require-Cmd composer
Require-Cmd php

$root = Resolve-Path "$PSScriptRoot\.."
Set-Location $root

# ----- 1. Pull down a stock Laravel 13 skeleton in a temp folder -----
Write-Host "==> Bootstrapping Laravel 13 skeleton into a temporary directory" -ForegroundColor Cyan
$tmp = Join-Path $env:TEMP "cuk-laravel-skeleton"
if (Test-Path $tmp) { Remove-Item -Recurse -Force $tmp }
# --no-scripts: skip post-create-project artisan calls that need vendor/ that doesn't exist yet.
Invoke-Native "composer create-project" {
    composer create-project laravel/laravel:^13.0 $tmp --no-install --no-scripts --quiet
}

# ----- 2. Merge — only files we don't already author -----
Write-Host "==> Merging skeleton (preserving authored files)" -ForegroundColor Cyan
Get-ChildItem -Path $tmp -Recurse -File -Force | ForEach-Object {
    $rel = $_.FullName.Substring($tmp.Length).TrimStart('\','/')
    $dst = Join-Path $root $rel
    if (-not (Test-Path $dst)) {
        $dstDir = Split-Path $dst -Parent
        if (-not (Test-Path $dstDir)) { New-Item -ItemType Directory -Force $dstDir | Out-Null }
        Copy-Item $_.FullName $dst
    }
}
Remove-Item -Recurse -Force $tmp

# ----- 3. Composer install -----
Write-Host "==> composer install (may take 1-3 min)" -ForegroundColor Cyan
Invoke-Native "composer install" {
    composer install --no-interaction --prefer-dist --no-scripts
}

# ----- 4. .env + app key -----
if (-not (Test-Path ".env")) {
    Copy-Item .env.example .env
    Write-Host "    .env created from .env.example" -ForegroundColor Yellow
}
php artisan key:generate --ansi --force 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { Write-Host "    key:generate non-zero exit (non-fatal)" -ForegroundColor Yellow }

# ----- 5. Ensure 15 module skeletons exist -----
Write-Host "==> Ensuring 15 module skeletons exist" -ForegroundColor Cyan
$modules = @(
    "AcademicStructure","Concours","UserManagement","Parametrage","Referentiels",
    "Scolarite","Enseignements","EmploiDuTemps","Evaluations","Examens",
    "ResultatsDiplomes","Presences","Finances","Communication","Reporting"
)
foreach ($m in $modules) {
    $modPath = Join-Path $root "Modules/$m/module.json"
    if (Test-Path $modPath) {
        Write-Host "    - $m (already exists, skipping)" -ForegroundColor DarkGray
    } else {
        Write-Host "    - $m (generating)"
        php artisan module:make $m 2>&1 | Out-Null
    }
}

# ----- 6. Composer-merge modules' autoload -----
Write-Host "==> composer dump-autoload" -ForegroundColor Cyan
composer dump-autoload 2>&1 | Out-Null

Write-Host ""
Write-Host "==> Done." -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1) Edit .env (DB, Redis, eBilling, reCAPTCHA secrets)"
Write-Host "  2) Drop the legacy MariaDB dump in storage/legacy/dump.sql (optional)"
Write-Host "  3) docker compose up -d --build"
Write-Host "  4) docker compose exec app php artisan migrate --seed"
Write-Host "  5) Visit http://localhost:8000/login"
