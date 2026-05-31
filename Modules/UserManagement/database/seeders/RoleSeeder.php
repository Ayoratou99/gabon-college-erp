<?php

declare(strict_types=1);

namespace Modules\UserManagement\Database\Seeders;

use App\Foundation\Permissions\Permission as PermissionValueObject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Models\Permission;
use Modules\UserManagement\Models\Role;

/**
 * Seeds the system roles + their default permission grants.
 *
 * Mapping of legacy `fonctions.code` → new role code:
 *
 *   Sup-Admin → super-admin
 *   DG        → dg
 *   DE        → de
 *   CC        → chef-centre
 *   (new)     → candidat   (assigned after results publication, Stage 5)
 *
 * Idempotent: safe to run on every migrate.
 */
final class RoleSeeder extends Seeder
{
    /** @var array<string, array{name: string, description: string, permissions: array<int, string>}> */
    private const ROLES = [
        'super-admin' => [
            'name'        => 'Super Administrateur',
            'description' => 'Accès complet à toutes les ressources et actions.',
            'permissions' => ['*:*:*'],
        ],
        'dg' => [
            'name'        => 'Directeur Général',
            'description' => 'Direction générale — accès lecture/écriture, sauf suppression d\'utilisateurs.',
            'permissions' => [
                'view:users:*', 'create:users:*', 'edit:users:*',
                'view:roles:*', 'view:permissions:*',
                '*:candidats:*', '*:centres:*', '*:sessions:*',
                '*:parametrage:*', '*:referentiels:*',
                'view:login_attempts:*',
                'publish:results:*',
                'view:payments:*',
                'manage:chef_centre_assignments:*',
                'view:audit_log:*',
                'view:reporting:*', 'export:reporting:*',
            ],
        ],
        'de' => [
            'name'        => 'Directeur des Études',
            'description' => 'Pilotage des concours et de la scolarité.',
            'permissions' => [
                'view:users:*',
                '*:candidats:*', '*:centres:*', '*:sessions:*',
                'view:parametrage:*', 'edit:parametrage:*',
                'view:referentiels:*', 'edit:referentiels:*',
                'publish:results:*',
                'view:payments:*',
                'manage:chef_centre_assignments:*',
                'view:login_attempts:*',
                'view:audit_log:*',
                'view:reporting:*', 'export:reporting:*',
            ],
        ],
        'chef-centre' => [
            'name'        => 'Chef de Centre',
            'description' => 'Gestion des candidats et épreuves de son centre.',
            'permissions' => [
                'view:users:own',
                'view:candidats:own_center',
                'edit:candidats:own_center',
                'validate:candidats:own_center',
                'reject:candidats:own_center',
                'view:centres:own_center',
                'view:sessions:own_session',
                'view:reporting:own_center',
            ],
        ],
        'candidat' => [
            'name'        => 'Candidat',
            'description' => 'Accès au dossier personnel et aux résultats (post-publication).',
            'permissions' => [
                'view:users:own',
                'edit:users:own',
                'view:candidats:own',
                'edit:candidats:own',
            ],
        ],
        'etudiant' => [
            'name'        => 'Étudiant',
            'description' => 'Candidat admis ayant activé son compte — accès à son espace personnel + dossier d\'admission.',
            'permissions' => [
                'view:users:own',
                'edit:users:own',
                'view:candidats:own',     // their original candidat dossier (read-only)
                'view:etudiant_space:own',
            ],
        ],
    ];

    public function run(): void
    {
        // 1. Catalog every permission string we'll need.
        $patterns = collect(self::ROLES)->pluck('permissions')->flatten()->unique();

        DB::transaction(function () use ($patterns): void {
            foreach ($patterns as $pattern) {
                PermissionValueObject::parse($pattern); // fail-fast on typos
                Permission::query()->updateOrCreate(
                    ['pattern' => $pattern],
                    ['module' => 'UserManagement'],
                );
            }

            // 2. Upsert each role + sync its permissions.
            foreach (self::ROLES as $code => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name'        => $definition['name'],
                        'description' => $definition['description'],
                        'is_system'   => true,
                    ],
                );

                $permissionIds = Permission::query()
                    ->whereIn('pattern', $definition['permissions'])
                    ->pluck('id')
                    ->all();

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
