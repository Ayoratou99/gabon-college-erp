<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ChefCentreAssignment;
use Modules\Concours\Models\ConcoursSession;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

/**
 * Demo: assign every user with the chef-centre role to a centre for the
 * active session, round-robin. In production the DG/DE will do this through
 * the back-office UI; this seeder just ensures the auth flow has data to
 * play with on day one.
 */
final class ChefCentreAssignmentsSeeder extends Seeder
{
    public function run(): void
    {
        $session = ConcoursSession::active();
        if ($session === null) {
            $this->command->warn('No active session — skipping ChefCentreAssignmentsSeeder.');
            return;
        }

        $roleId = Role::query()->where('code', 'chef-centre')->value('id');
        if ($roleId === null) {
            return;
        }

        $chefs = User::query()
            ->whereHas('roles', static fn ($q) => $q->where('roles.id', $roleId))
            ->get();

        if ($chefs->isEmpty()) {
            $this->command->warn('No chef-centre users imported — skipping.');
            return;
        }

        $centres = Centre::query()->where('active', true)->orderBy('display_order')->get();
        if ($centres->isEmpty()) {
            return;
        }

        $i = 0;
        foreach ($chefs as $chef) {
            $centre = $centres[$i % $centres->count()];
            ChefCentreAssignment::query()->updateOrCreate(
                [
                    'concours_session_id' => $session->id,
                    'centre_id'           => $centre->id,
                    'user_id'             => $chef->id,
                ],
                [
                    'est_principal' => true,
                    'assigned_at'   => now(),
                ],
            );
            $i++;
        }
    }
}
