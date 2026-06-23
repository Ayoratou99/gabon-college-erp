<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Models\Permission;
use Modules\UserManagement\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalog of roles + permissions, plus a super-admin permission editor.
 *
 * Roles are seeded from code (RoleSeeder) as the baseline. The editor lets a
 * super-admin (edit:roles:*) tune which permissions each role holds without a
 * redeploy. NOTE: re-running RoleSeeder re-syncs permissions from code and so
 * overwrites edits made here — the seeder remains the source of truth on deploy.
 */
final class RolePageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:roles:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $roles = Role::query()
            ->with(['permissions:id,pattern,module'])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        // Full catalog — the assignable universe rendered as checkboxes.
        $allPermissions = Permission::query()
            ->orderBy('module')->orderBy('pattern')
            ->get(['id', 'pattern', 'module']);

        return view('usermanagement::admin.roles.index', [
            'roles'            => $roles,
            'allPermissions'   => $allPermissions,
            'totalPermissions' => $allPermissions->count(),
            'canEdit'          => $this->checker->can($request->user(), 'edit:roles:*'),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:roles:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // The super-admin role is the RBAC backstop. Never let it be narrowed
        // from the UI — a bad save could lock every administrator out.
        if ($role->code === 'super-admin') {
            return back()->with('status', 'Le rôle « Super Administrateur » est protégé et ne peut pas être modifié.');
        }

        $data = $request->validate([
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('status', "Permissions du rôle « {$role->name} » mises à jour.");
    }
}
