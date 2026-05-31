<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Modules\UserManagement\Models\User;

$user = User::query()->where('telephone', '060000000')->firstOrFail();
auth('web')->setUser($user);

$verifiedKey = config('usermanagement.two_factor.session_keys.verified');

/** @return array{status: int, body: string} */
function call(string $name, string $method, array $params = [], array $payload = [], ?string $verifiedKey = null): array
{
    $url = route($name, $params);
    $path = parse_url($url, PHP_URL_PATH) ?: '/';

    $req = Request::create($path, $method, $payload);
    $req->setUserResolver(fn () => auth('web')->user());

    // Fake a session that's already 2FA-verified.
    $session = app('session.store');
    $session->setId('smoke-' . bin2hex(random_bytes(8)));
    $session->start();
    if ($verifiedKey !== null) {
        $session->put($verifiedKey, true);
    }
    $session->put('_token', 'smoke-token');
    $req->setLaravelSession($session);
    if ($method === 'POST') {
        $payload['_token'] = 'smoke-token';
        $req = Request::create($path, $method, $payload);
        $req->setUserResolver(fn () => auth('web')->user());
        $req->setLaravelSession($session);
    }

    /** @var \Illuminate\Foundation\Http\Kernel $kernel */
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $resp = $kernel->handle($req);

    return ['status' => $resp->getStatusCode(), 'body' => (string) $resp->getContent()];
}

$tests = [
    ['admin.referentiels.index', 'GET',  ['slug' => 'nationalites']],
    ['admin.referentiels.data',  'POST', ['slug' => 'nationalites'], ['draw' => 1, 'start' => 0, 'length' => 10]],
    ['admin.referentiels.index', 'GET',  ['slug' => 'series-bac']],
    ['admin.academic.index',     'GET',  ['slug' => 'cycles']],
    ['admin.academic.data',      'POST', ['slug' => 'cycles'], ['draw' => 1, 'start' => 0, 'length' => 10]],
    ['admin.academic.index',     'GET',  ['slug' => 'niveaux']],
    ['admin.pages.concours.candidats.index', 'GET',  []],
    ['admin.pages.concours.candidats.data',  'POST', [], ['draw' => 1, 'start' => 0, 'length' => 10]],
    ['admin.pages.concours.epreuves.index',  'GET',  []],
    ['admin.pages.concours.epreuves.data',   'POST', [], ['draw' => 1, 'start' => 0, 'length' => 10]],
];

foreach ($tests as $t) {
    [$name, $method, $params] = $t;
    $payload = $t[3] ?? [];
    try {
        $r = call($name, $method, $params, $payload, $verifiedKey);
        $tag = $r['status'] === 200 ? 'OK' : 'FAIL';
        echo sprintf("%-6s %-44s %s %d   %s\n", $tag, $name, $method, $r['status'], $r['status'] === 200 ? '' : substr($r['body'], 0, 200));
    } catch (\Throwable $e) {
        echo sprintf("ERROR  %-44s %s   %s\n", $name, $method, $e->getMessage());
    }
}

// Show the actual JSON shape returned by the data endpoints for one slug each.
echo "\n--- Sample DT JSON: referentiels nationalites ---\n";
$r = call('admin.referentiels.data', 'POST', ['slug' => 'nationalites'], ['draw' => 1, 'start' => 0, 'length' => 3], $verifiedKey);
print_r(json_decode($r['body'] ?: '{}', true));

echo "\n--- Sample DT JSON: academic cycles ---\n";
$r = call('admin.academic.data', 'POST', ['slug' => 'cycles'], ['draw' => 1, 'start' => 0, 'length' => 3], $verifiedKey);
print_r(json_decode($r['body'] ?: '{}', true));

echo "\n--- Sample DT JSON: candidats ---\n";
$r = call('admin.pages.concours.candidats.data', 'POST', [], ['draw' => 1, 'start' => 0, 'length' => 3], $verifiedKey);
print_r(json_decode($r['body'] ?: '{}', true));

// Search test: only Gabon should match.
echo "\n--- Search: nationalites where search=Gabon ---\n";
$r = call('admin.referentiels.data', 'POST', ['slug' => 'nationalites'], [
    'draw' => 2, 'start' => 0, 'length' => 5,
    'search' => ['value' => 'Gabon'],
    'columns' => [
        ['data' => 'code_iso'], ['data' => 'nom'], ['data' => 'display_order'], ['data' => 'active'],
    ],
], $verifiedKey);
$j = json_decode($r['body'], true);
echo "recordsTotal=" . $j['recordsTotal'] . " recordsFiltered=" . $j['recordsFiltered'] . "\n";
foreach ($j['data'] as $row) echo "  - {$row['code_iso']}  {$row['nom']}\n";

// Sort test: order by nom desc.
echo "\n--- Sort: cycles ordered by nom desc ---\n";
$r = call('admin.academic.data', 'POST', ['slug' => 'cycles'], [
    'draw' => 3, 'start' => 0, 'length' => 5,
    'order' => [['column' => 1, 'dir' => 'desc']],
    'columns' => [
        ['data' => 'code'], ['data' => 'nom'], ['data' => 'duree_annees'], ['data' => 'active'],
    ],
], $verifiedKey);
$j = json_decode($r['body'], true);
foreach ($j['data'] as $row) echo "  - {$row['code']}  {$row['nom']}\n";
