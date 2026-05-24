<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Http\Controllers;

use App\Foundation\Http\Contracts\ResourceRegistry;
use App\Foundation\Http\Controllers\ResourceCrudController;
use App\Foundation\Permissions\PermissionChecker;
use Modules\AcademicStructure\Services\AcademicResourceRegistry;

final class AcademicResourceController extends ResourceCrudController
{
    public function __construct(
        PermissionChecker $checker,
        private readonly AcademicResourceRegistry $registry,
    ) {
        parent::__construct($checker);
    }

    protected function registry(): ResourceRegistry
    {
        return $this->registry;
    }
}
