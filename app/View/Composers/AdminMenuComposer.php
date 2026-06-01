<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\View\View;

/**
 * Builds the sidebar menu, filtered by what the current user can see.
 *
 * The menu is declared statically here (single source of truth for
 * navigation). Each item lists the *minimum* permission required; the
 * filter pass drops items the holder doesn't have.
 *
 * Headers act as section dividers — they remain only if at least one
 * item below them survived the filter.
 */
final class AdminMenuComposer
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly PermissionChecker $checker,
    ) {}

    public function compose(View $view): void
    {
        $view->with('adminMenu', $this->filtered($this->definition()));
    }

    /** @return list<array<string, mixed>> */
    private function definition(): array
    {
        return [
            ['type' => 'item',   'label' => 'Tableau de bord',     'icon' => 'fas fa-tachometer-alt', 'route' => 'dashboard',                        'permission' => null],
            // Sessions are global (they will eventually tie concours +
            // scolarité + examens together) — keep them at the top level,
            // outside the Concours sub-menu.
            ['type' => 'item',   'label' => 'Sessions',            'icon' => 'fas fa-calendar-check', 'route' => 'admin.pages.concours.sessions.index', 'permission' => 'view:sessions:*'],

            ['type' => 'header', 'label' => 'Concours'],
            ['type' => 'item',   'label' => 'Candidats',           'icon' => 'fas fa-user-graduate', 'route' => 'admin.pages.concours.candidats.index', 'permission' => 'view:candidats:*'],
            ['type' => 'item',   'label' => 'Épreuves',            'icon' => 'fas fa-pen-nib',       'route' => 'admin.pages.concours.epreuves.index',  'permission' => 'view:epreuves:*'],
            ['type' => 'item',   'label' => 'Emploi du temps',     'icon' => 'far fa-calendar-alt',  'route' => 'admin.pages.concours.planning.index',  'permission' => 'view:planning:own_center'],
            ['type' => 'item',   'label' => 'Notes',               'icon' => 'fas fa-marker',        'route' => 'admin.pages.concours.notes.picker',    'permission' => 'view:notes:own_center'],
            ['type' => 'item',   'label' => 'Sélection',           'icon' => 'fas fa-trophy',        'route' => 'admin.pages.concours.selection.wizard','permission' => 'publish:results:*'],
            ['type' => 'item',   'label' => 'Paiements',           'icon' => 'fas fa-credit-card',   'route' => 'admin.pages.concours.payments.index',  'permission' => 'view:payments:*'],
            ['type' => 'item',   'label' => 'Centres',             'icon' => 'fas fa-building-columns', 'route' => 'admin.pages.concours.centres.index',   'permission' => 'edit:centres:*'],
            ['type' => 'item',   'label' => 'Chefs de centre',     'icon' => 'fas fa-user-tie',      'route' => 'admin.pages.concours.chef_centres.index', 'permission' => 'manage:chef_centre_assignments:*'],

            ['type' => 'header', 'label' => 'Décisionnel'],
            ['type' => 'item',   'label' => 'Reporting',           'icon' => 'fas fa-chart-line',    'route' => 'admin.pages.reporting.dashboard',      'permission' => 'view:reporting:own_center'],

            ['type' => 'header', 'label' => 'Configuration'],
            ['type' => 'item',   'label' => 'Référentiels',        'icon' => 'fas fa-database',          'route' => 'admin.referentiels.index',   'route_params' => ['slug' => 'nationalites'], 'permission' => 'view:referentiels_nationalites:*'],
            ['type' => 'item',   'label' => 'Pièces × Sections',   'icon' => 'fas fa-table-cells',       'route' => 'admin.pages.concours.document_requis_sections.index', 'permission' => 'edit:referentiels:*'],
            ['type' => 'item',   'label' => 'Structure académique','icon' => 'fas fa-graduation-cap',    'route' => 'admin.academic.index',       'route_params' => ['slug' => 'cycles'],       'permission' => 'view:academic_cycles:*'],
            ['type' => 'item',   'label' => 'Paramétrage',         'icon' => 'fas fa-sliders',           'route' => 'admin.pages.parametrage.index', 'permission' => 'view:parametrage:*'],
            ['type' => 'item',   'label' => 'Documents officiels', 'icon' => 'far fa-file-lines',        'route' => 'admin.pages.parametrage.documents.index', 'permission' => 'view:parametrage:*'],

            ['type' => 'header', 'label' => 'Sécurité'],
            ['type' => 'item',   'label' => 'Utilisateurs',        'icon' => 'fas fa-users',  'route' => 'admin.pages.users.index',           'permission' => 'view:users:*'],
            ['type' => 'item',   'label' => 'Rôles & permissions', 'icon' => 'fas fa-key',    'route' => 'admin.pages.roles.index',           'permission' => 'view:roles:*'],
            ['type' => 'item',   'label' => 'Tentatives de login', 'icon' => 'fas fa-eye',    'route' => 'admin.pages.login-attempts.index',  'permission' => 'view:login_attempts:*'],
            ['type' => 'item',   'label' => 'Journal d\'audit',    'icon' => 'fas fa-list-check', 'route' => 'admin.pages.audit-log.index',     'permission' => 'view:audit_log:*'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filtered(array $items): array
    {
        $user = $this->auth->guard()->user();

        $passed = array_values(array_filter($items, function (array $item) use ($user): bool {
            if (($item['type'] ?? null) === 'header') {
                return true; // keep for now; drop trailing/orphan headers later
            }
            $permission = $item['permission'] ?? null;
            if ($permission === null) {
                return true; // public to any authenticated user
            }
            return $user !== null && $this->checker->can($user, $permission);
        }));

        // Drop headers that no longer have a following item.
        $result = [];
        foreach ($passed as $i => $item) {
            if (($item['type'] ?? null) === 'header') {
                $next = $passed[$i + 1] ?? null;
                if ($next === null || ($next['type'] ?? null) === 'header') {
                    continue;
                }
            }
            $result[] = $item;
        }

        return $result;
    }
}
