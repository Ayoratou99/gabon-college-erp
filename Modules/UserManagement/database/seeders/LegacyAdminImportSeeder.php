<?php

declare(strict_types=1);

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\LegacyDumpParser;
use Ramsey\Uuid\Uuid;

/**
 * Imports the 13 historical admin accounts from the legacy MariaDB dump.
 *
 *   - utilisateurs   → users (telephone = login identifier, password stored as
 *                              SHA1 with password_legacy=true → rehashed on
 *                              first successful login)
 *   - fonctions      → user_role (Sup-Admin → super-admin, etc.)
 *
 * Skipped silently if the dump file isn't present (CI/test runs).
 * Idempotent: keyed on `telephone` so it can re-run safely.
 */
final class LegacyAdminImportSeeder extends Seeder
{
    private const ROLE_MAP = [
        'Sup-Admin' => 'super-admin',
        'DG'        => 'dg',
        'DE'        => 'de',
        'CC'        => 'chef-centre',
    ];

    public function run(): void
    {
        $path = (string) config('usermanagement.legacy_dump_path');
        if (! is_file($path)) {
            $this->command->warn("LegacyAdminImportSeeder: dump not found at {$path}, skipping.");
            return;
        }

        $parser = LegacyDumpParser::fromFile($path);

        $usersByLegacyId = $this->importUsers($parser);
        $this->assignRoles($parser, $usersByLegacyId);

        $this->command->info(sprintf(
            'LegacyAdminImportSeeder: %d users imported, %d users with role assignments.',
            count($usersByLegacyId),
            count($usersByLegacyId),
        ));
    }

    /** @return array<string, string>  legacy idut → new uuid */
    private function importUsers(LegacyDumpParser $parser): array
    {
        $map = [];

        DB::transaction(function () use ($parser, &$map): void {
            foreach ($parser->rowsOf('utilisateurs') as $row) {
                $legacyId = (string) ($row['idut'] ?? '');
                $tel      = (string) ($row['tel'] ?? '');
                $sha1Hex  = mb_strtolower(trim((string) ($row['mp'] ?? '')));
                if ($legacyId === '' || $tel === '') {
                    continue;
                }

                $existing = User::query()->where('telephone', $tel)->first();
                if ($existing !== null) {
                    $map[$legacyId] = (string) $existing->getKey();
                    continue;
                }

                // IMPORTANT: bypass the `password` Eloquent cast. The cast is
                // configured to bcrypt any value assigned via Eloquent — that
                // would store bcrypt(sha1_hex) instead of the raw SHA1, and
                // the LegacyPasswordRehasher would never match. We insert
                // through the query builder so the raw 40-char SHA1 hex lands
                // verbatim in the column.
                $g2faSecret = $row['google_two_factor_secret'] ?: null;
                $userId = (method_exists(Uuid::class, 'uuid7') ? Uuid::uuid7() : Uuid::uuid4())->toString();

                DB::table('users')->insert([
                    'id'                     => $userId,
                    'nom'                    => (string) ($row['nom'] ?? ''),
                    'prenom'                 => (string) ($row['prenom'] ?? ''),
                    'telephone'              => $tel,
                    'email'                  => null,
                    'password'               => $sha1Hex,
                    'password_legacy'        => true,
                    'must_set_password'      => false,
                    'google2fa_secret'       => $g2faSecret !== null ? Crypt::encryptString($g2faSecret) : null,
                    'google2fa_confirmed_at' => $g2faSecret !== null ? now() : null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);

                $map[$legacyId] = $userId;
            }
        });

        return $map;
    }

    /** @param array<string, string> $usersByLegacyId */
    private function assignRoles(LegacyDumpParser $parser, array $usersByLegacyId): void
    {
        $rolesByCode = Role::query()->pluck('id', 'code')->all();

        // Take the most recent function per user (rows are dumped chronologically).
        $latestPerUser = [];
        foreach ($parser->rowsOf('fonctions') as $row) {
            $latestPerUser[(string) $row['idut']] = (string) $row['code'];
        }

        DB::transaction(function () use ($latestPerUser, $usersByLegacyId, $rolesByCode): void {
            foreach ($latestPerUser as $legacyUserId => $functionCode) {
                $userId = $usersByLegacyId[$legacyUserId] ?? null;
                if ($userId === null) {
                    continue;
                }
                $roleCode = self::ROLE_MAP[$functionCode] ?? null;
                if ($roleCode === null || ! isset($rolesByCode[$roleCode])) {
                    continue;
                }

                DB::table('user_role')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $rolesByCode[$roleCode]],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }
}
