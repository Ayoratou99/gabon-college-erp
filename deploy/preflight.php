<?php

declare(strict_types=1);

/**
 * Shared-hosting pre-flight check. Run:  php deploy/preflight.php
 *
 * Verifies the things that silently break a Laravel + PostgreSQL app on
 * mutualised hosting: PHP version, required extensions (esp. pdo_pgsql, which
 * many hosts ship MySQL-only), writable dirs, and that key binaries exist.
 * Exits non-zero on any hard failure so deploy.sh aborts early.
 */

$fail = 0;
$line = static function (string $status, string $msg): void {
    $c = ['OK' => "\033[1;32m", 'FAIL' => "\033[1;31m", 'WARN' => "\033[1;33m"][$status] ?? '';
    printf("  %s%-4s\033[0m %s\n", $c, $status, $msg);
};

echo "PHP version + extensions\n";

// --- PHP version (composer requires ^8.4) ---
if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
    $line('OK', 'PHP ' . PHP_VERSION);
} else {
    $line('FAIL', 'PHP ' . PHP_VERSION . ' — this app requires 8.4+. Switch the PHP version in your hosting panel (and use the matching CLI, e.g. php8.4).');
    $fail++;
}

// --- Required extensions ---
$required = [
    'pdo_pgsql' => 'PostgreSQL driver — WITHOUT THIS THE APP CANNOT CONNECT. Many shared hosts enable it per-domain in the panel.',
    'pgsql'     => 'PostgreSQL client library.',
    'mbstring'  => 'Multibyte strings (accents).',
    'openssl'   => 'Encryption (APP_KEY, 2FA secrets).',
    'fileinfo'  => 'Uploaded-file MIME detection.',
    'ctype'     => 'Required by the framework.',
    'json'      => 'Required by the framework.',
    'tokenizer' => 'Required by the framework.',
    'xml'       => 'Required by the framework.',
    'dom'       => 'PDF generation (dompdf) + XML.',
    'curl'      => 'eBilling HTTP calls.',
    'gd'        => 'Image handling / QR code rendering for 2FA + PDFs.',
    'zip'       => 'composer install (prefer-dist).',
    'bcmath'    => 'Precise money / numeric handling.',
];
foreach ($required as $ext => $why) {
    if (extension_loaded($ext)) {
        $line('OK', "ext-{$ext}");
    } else {
        // gd/zip are warn-level (degrade gracefully); pdo_pgsql etc. are hard fails.
        $hard = ! in_array($ext, ['gd', 'zip', 'bcmath'], true);
        $line($hard ? 'FAIL' : 'WARN', "ext-{$ext} MISSING — {$why}");
        if ($hard) { $fail++; }
    }
}

echo "\nWritable directories\n";
$root = dirname(__DIR__);
$writables = [
    'storage',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];
foreach ($writables as $rel) {
    $abs = $root . DIRECTORY_SEPARATOR . $rel;
    if (! is_dir($abs)) {
        // Create the framework sub-dirs that git doesn't track.
        @mkdir($abs, 0775, true);
    }
    if (is_dir($abs) && is_writable($abs)) {
        $line('OK', "{$rel}/ writable");
    } else {
        $line('FAIL', "{$rel}/ NOT writable — run: chmod -R ug+rwX storage bootstrap/cache");
        $fail++;
    }
}

echo "\nEnvironment\n";
$envPath = $root . '/.env';
if (is_file($envPath)) {
    $line('OK', '.env present');
    $env = (string) file_get_contents($envPath);
    if (preg_match('/^APP_KEY=base64:.{10,}$/m', $env)) {
        $line('OK', 'APP_KEY set');
    } else {
        $line('FAIL', 'APP_KEY missing/empty — set the SAME key as the DB dump source, then re-run.');
        $fail++;
    }
    if (preg_match('/^APP_DEBUG=false$/m', $env)) {
        $line('OK', 'APP_DEBUG=false');
    } else {
        $line('WARN', 'APP_DEBUG should be false in production.');
    }
    if (preg_match('/^DB_CONNECTION=pgsql$/m', $env)) {
        $line('OK', 'DB_CONNECTION=pgsql');
    } else {
        $line('WARN', 'DB_CONNECTION is not pgsql — this app was built for PostgreSQL.');
    }
} else {
    $line('WARN', '.env not found yet (cp .env.production.example .env and fill it).');
}

echo "\n";
if ($fail > 0) {
    printf("\033[1;31m%d blocking issue(s) — fix the FAIL lines above before serving.\033[0m\n", $fail);
    exit(1);
}
echo "\033[1;32mAll pre-flight checks passed.\033[0m\n";
exit(0);
