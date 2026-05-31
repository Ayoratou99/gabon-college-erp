<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

$session = ConcoursSession::active();
if (! $session) { echo "No active session — abort.\n"; exit(1); }

$email = 'smoke.activation@test.local';
$tel   = '060555' . random_int(1000, 9999);

// Clean up any previous run.
User::query()->whereRaw('LOWER(email) = ?', [$email])->forceDelete();

// 1. Insert a minimal candidat marked admis with the must_set_password User.
$role = Role::query()->where('code', 'candidat')->value('id');

$user = User::query()->create([
    'nom'                       => 'SMOKE',
    'prenom'                    => 'Tester',
    'email'                     => $email,
    'telephone'                 => $tel,
    'password'                  => null,
    'must_set_password'         => true,
    'promoted_from_candidat_id' => null,
]);
if ($role) {
    $user->roles()->syncWithoutDetaching([$role]);
}

echo "Seeded user id={$user->id} email={$email} tel={$tel} needs_activation=" . ($user->needsActivation() ? 'YES' : 'NO') . "\n";

// 2. Simulate the login attempt → should redirect to /connexion/premiere-fois.
function visit(string $method, string $path, array $payload = [], ?\Illuminate\Session\Store $session = null): array
{
    $session ??= app('session.store');
    if (! $session->isStarted()) {
        $session->setId('smoke-' . bin2hex(random_bytes(8)));
        $session->start();
    }
    $session->put('_token', 'smoke-token');
    if ($method === 'POST') { $payload['_token'] = 'smoke-token'; }

    $req = Request::create($path, $method, $payload);
    $req->setLaravelSession($session);
    $resp = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);
    return [
        'status'   => $resp->getStatusCode(),
        'body'     => (string) $resp->getContent(),
        'redirect' => $resp->headers->get('Location'),
        'session'  => $session,
    ];
}

// First-login: identify step
$ses = app('session.store');
$r = visit('POST', '/connexion/premiere-fois', ['email' => $email, 'telephone' => $tel], $ses);
echo "[identify]   status={$r['status']} redirect={$r['redirect']}\n";

if (! str_contains((string) $r['redirect'], 'mot-de-passe')) {
    echo "  body: " . substr($r['body'], 0, 400) . "\n";
    echo "FAIL\n"; exit(1);
}

// Password step
$r = visit('POST', '/connexion/premiere-fois/mot-de-passe', [
    'password' => 'TempP@ssw0rd!',
    'password_confirmation' => 'TempP@ssw0rd!',
], $r['session']);
echo "[password]   status={$r['status']} redirect={$r['redirect']}\n";

$user->refresh();
echo "  user.password now set: " . ($user->getAttribute('password') !== null ? 'YES' : 'NO') . "\n";
echo "  must_set_password: " . ($user->must_set_password ? 'YES' : 'NO') . "\n";

// 2FA step (will show enrol form)
$r = visit('GET', '/connexion/premiere-fois/2fa', [], $r['session']);
echo "[2fa form]   status={$r['status']}\n";

// Login flow: try regular login with the new password → should require 2FA challenge
$r2 = visit('POST', '/login', ['identifier' => $email, 'password' => 'TempP@ssw0rd!']);
echo "[regular login attempt] status={$r2['status']} redirect={$r2['redirect']}\n";

// Try with wrong password for a user with must_set_password=true → should redirect to first-login
User::query()->where('id', $user->id)->update(['must_set_password' => true]);
$r3 = visit('POST', '/login', ['identifier' => $email, 'password' => 'anything']);
echo "[login while must_set_password=true] status={$r3['status']} redirect={$r3['redirect']}\n";

// Cleanup
$user->forceDelete();
echo "\nDone, cleaned up test user.\n";
