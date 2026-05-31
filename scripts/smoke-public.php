<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;

/** @return array{status: int, body: string, redirect: ?string} */
function visit(string $method, string $path, array $payload = []): array
{
    $session = app('session.store');
    $session->setId('smoke-' . bin2hex(random_bytes(8)));
    $session->start();
    $session->put('_token', 'smoke-token');
    if ($method === 'POST') {
        $payload['_token'] = 'smoke-token';
    }

    $req = Request::create($path, $method, $payload);
    $req->setLaravelSession($session);

    /** @var \Illuminate\Foundation\Http\Kernel $kernel */
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $resp = $kernel->handle($req);

    return [
        'status'   => $resp->getStatusCode(),
        'body'     => (string) $resp->getContent(),
        'redirect' => $resp->headers->get('Location'),
    ];
}

$tests = [
    ['GET',  '/'],
    ['GET',  '/verifier-demande'],
    ['POST', '/verifier-demande', ['q' => 'CUK-NONEXISTENT0000']],
    ['POST', '/verifier-demande', ['q' => 'Gabon']],
    ['GET',  '/resultats'],
    ['GET',  '/login'],
    ['GET',  '/connexion/premiere-fois'],
    ['POST', '/connexion/premiere-fois', ['email' => 'nobody@example.com', 'telephone' => '060000000']],
    ['GET',  '/connexion/premiere-fois/mot-de-passe'],
];

function highlight(string $body, string $needle): string
{
    return str_contains($body, $needle) ? "✓ found '$needle'" : "✗ missing '$needle'";
}

foreach ($tests as $t) {
    [$method, $path] = $t;
    $payload = $t[2] ?? [];
    try {
        $r = visit($method, $path, $payload);
        $ok = $r['status'] < 500;
        $tag = $ok ? 'OK' : 'FAIL';
        $extra = $r['redirect'] ? '→ ' . $r['redirect'] : '';
        echo sprintf("%-6s %-5s %-44s %d  %s\n", $tag, $method, $path, $r['status'], $extra);
        if (! $ok) {
            echo "  body[0..200]: " . substr($r['body'], 0, 200) . "\n";
        }
    } catch (\Throwable $e) {
        echo "ERROR  $method $path  " . $e->getMessage() . "\n";
    }
}

echo "\n--- Content checks ---\n";
$home = visit('GET', '/');
echo highlight($home['body'], 'Connexion'),                  "  (navbar relabeled)\n";
echo highlight($home['body'], 'btn-cuk-primary'),            "  (themed CTA class in nav)\n";
echo highlight($home['body'], 'feature-card'),               "  (feature card markup)\n";

$lookup = visit('GET', '/verifier-demande');
echo highlight($lookup['body'], 'Matricule, nom, email ou téléphone'), "  (new lookup placeholder)\n";

$login = visit('GET', '/login');
echo highlight($login['body'], 'Première connexion'),        "  (first-login hint on login page)\n";

$first = visit('GET', '/connexion/premiere-fois');
echo highlight($first['body'], 'Étape 1 sur 3'),             "  (first-login identify step)\n";

$results = visit('GET', '/resultats');
echo highlight($results['body'], 'Résultats du concours'),    "  (results page heading)\n";
