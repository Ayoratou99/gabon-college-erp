<?php

declare(strict_types=1);

namespace Modules\Parametrage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Parametrage\Models\Setting;

/**
 * Validates the inbound value against the setting's *declared* type and
 * its custom `validation_rules` JSON column (when present).
 *
 * Validation flow:
 *   1. The route binds `{setting}` to a Setting model.
 *   2. We pull its declared type and validation_rules.
 *   3. The "value" payload is validated against [type-shape, custom-rules].
 *
 * Authorization is handled by SettingPolicy@update.
 */
final class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = $this->route('setting');
        return $setting instanceof Setting
            && $this->user()?->can('edit:parametrage:*', $setting) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Setting $setting */
        $setting = $this->route('setting');

        $base = match ($setting->type) {
            'string', 'text', 'image_url' => ['required', 'string', 'max:65535'],
            'integer'                     => ['required', 'integer'],
            'decimal'                     => ['required', 'numeric'],
            'boolean'                     => ['required', 'boolean'],
            'json'                        => ['required', 'array'],
            'email'                       => ['required', 'email:rfc'],
            'phone'                       => ['required', 'string', 'regex:/^[+0-9 .-]{6,30}$/'],
            'url'                         => ['required', 'url'],
            default                       => ['required'],
        };

        return [
            'value' => array_merge($base, (array) ($setting->validation_rules ?? [])),
        ];
    }

    /** @return mixed */
    public function newValue(): mixed
    {
        return $this->validated('value');
    }
}
