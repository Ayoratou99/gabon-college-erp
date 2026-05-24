<?php

declare(strict_types=1);

namespace Modules\Parametrage\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Parametrage\Http\Requests\UpdateSettingRequest;
use Modules\Parametrage\Http\Resources\SettingResource;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Services\SettingsService;

/**
 * Admin API for the settings store.
 *
 *   GET    /api/parametrage                      list, optionally filter by ?category=
 *   GET    /api/parametrage/{setting}            single setting
 *   PUT    /api/parametrage/{setting}            update value
 *   GET    /api/parametrage/public               key→value map of all is_public=true settings
 *                                                 (no auth required — drives the homepage)
 *
 * The destructive endpoints (delete/create) are intentionally absent: settings
 * are a declared schema; new keys appear only through the seeder.
 */
final class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Setting::query()->orderBy('category')->orderBy('display_order')->orderBy('key');

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        return SettingResource::collection($query->get())->response();
    }

    public function show(Setting $setting): SettingResource
    {
        $this->authorize('view', $setting);
        return new SettingResource($setting);
    }

    public function update(UpdateSettingRequest $request, Setting $setting): SettingResource
    {
        $this->authorize('update', $setting);

        $this->settings->set(
            key: $setting->key,
            value: $request->newValue(),
            author: $request->user(),
            ipAddress: $request->ip(),
        );

        return new SettingResource($setting->refresh());
    }

    public function publicMap(): JsonResponse
    {
        return response()->json($this->settings->publicMap());
    }
}
