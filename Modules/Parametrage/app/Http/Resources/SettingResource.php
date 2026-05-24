<?php

declare(strict_types=1);

namespace Modules\Parametrage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Services\SettingValueCaster;

/**
 * @mixin Setting
 */
final class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SettingValueCaster $caster */
        $caster = app(SettingValueCaster::class);

        return [
            'id'            => $this->id,
            'key'           => $this->key,
            'category'      => $this->category,
            'type'          => $this->type,
            'label'         => $this->label,
            'description'   => $this->description,
            'is_encrypted'  => $this->is_encrypted,
            'is_public'     => $this->is_public,
            'is_system'     => $this->is_system,
            'display_order' => $this->display_order,
            // Hide encrypted values from non-super-admins; surface a sentinel.
            'value'         => $this->maskedValue($request, $caster),
            'form_input'    => $caster->formInputType($this->type),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }

    private function maskedValue(Request $request, SettingValueCaster $caster): mixed
    {
        if ($this->is_encrypted && ! $request->user()?->hasRole('super-admin')) {
            return '••••••••';
        }
        return $caster->deserialize($this->resource);
    }
}
