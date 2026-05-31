<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Models\Permission;
use Modules\UserManagement\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only catalog of roles + permissions for super-admin / DG to audit
 * the RBAC matrix. Editing roles is intentionally out of scope here — they
 * are seeded from code (RoleSeeder) and considered immutable in production.
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

        $totalPermissions = Permission::query()->count();

        return view('usermanagement::admin.roles.index', [
            'roles'            => $roles,
            'totalPermissions' => $totalPermissions,
        ]);
    }
}
