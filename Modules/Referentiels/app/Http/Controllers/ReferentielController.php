<?php

declare(strict_types=1);

namespace Modules\Referentiels\Http\Controllers;

use App\Foundation\Http\Contracts\ResourceRegistry;
use App\Foundation\Http\Controllers\ResourceCrudController;
use App\Foundation\Permissions\PermissionChecker;
use Modules\Referentiels\Services\ReferentielRegistry;

/**
 * Thin subclass — all the CRUD plumbing lives in the Foundation base.
 * The whole module's HTTP surface boils down to "bind the registry".
 */
final class ReferentielController extends ResourceCrudController
{
    public function __construct(
        PermissionChecker $checker,
        private readonly ReferentielRegistry $registry,
    ) {
        parent::__construct($checker);
    }

    protected function registry(): ResourceRegistry
    {
        return $this->registry;
    }
}
