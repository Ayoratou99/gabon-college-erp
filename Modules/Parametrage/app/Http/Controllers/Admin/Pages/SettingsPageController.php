<?php

declare(strict_types=1);

namespace Modules\Parametrage\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Models\SettingChangeLog;
use Modules\Parametrage\Services\SettingsService;
use Modules\Parametrage\Services\SettingValueCaster;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-rendered admin page for the Parametrage settings store.
 *
 *   GET /admin/parametrage[?cat=site]   → tabs per category + editable forms
 *
 * Mutations go through the existing JSON API (PUT /admin/parametrage/{id})
 * called by an Alpine component — no duplicated business logic here.
 */
final class SettingsPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
        private readonly SettingValueCaster $caster,
        private readonly FilesystemManager $files,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:parametrage:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $categories = (array) config('parametrage.categories', []);
        $active = $request->string('cat')->toString() ?: 'site';
        if (! array_key_exists($active, $categories)) {
            $active = (string) array_key_first($categories);
        }

        $settings = Setting::query()
            ->where('category', $active)
            ->orderBy('display_order')
            ->orderBy('key')
            ->get();

        // Pre-resolve every value so the view receives ready-to-display data.
        $rendered = $settings->map(function (Setting $s): array {
            $isSuperAdmin = optional(request()->user())->hasRole('super-admin') === true;
            $hideValue    = $s->is_encrypted && ! $isSuperAdmin;

            return [
                'model'      => $s,
                'value'      => $hideValue ? null : $this->caster->deserialize($s),
                'input_type' => $this->caster->formInputType($s->type),
                'hidden'     => $hideValue,
            ];
        });

        $canEdit = $this->checker->can($request->user(), 'edit:parametrage:*');

        return view('parametrage::admin.index', [
            'categories' => $categories,
            'active'     => $active,
            // NB: cannot use 'settings' here — the Parametrage view composer
            // also shares a global $settings key=>value map with every view.
            'rows'       => $rendered,
            'canEdit'    => $canEdit,
        ]);
    }

    /**
     * Audit-log browse — surfaces the existing `setting_change_logs` table
     * which was being written but never read. Read-only.
     *
     *   GET /admin/parametrage/historique
     */
    public function history(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:parametrage:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Encrypted values are stored as literal '[encrypted]' in the log,
        // so there's nothing to redact — the writer already did that.
        $entries = SettingChangeLog::query()
            ->with(['setting:id,key,label,category,type,is_encrypted', 'user:id,nom,prenom,email'])
            ->orderByDesc('changed_at')
            ->limit(100)
            ->get();

        $categories = (array) config('parametrage.categories', []);

        return view('parametrage::admin.history', [
            'entries'    => $entries,
            'categories' => $categories,
        ]);
    }

    /**
     * Inline file upload for `image_url` settings. The admin picks (or
     * drag-drops) a file, we validate + persist it on the `public` disk,
     * then store the resulting URL through SettingsService::set() so the
     * change goes through the same audit + cache flush as a manual edit.
     *
     *   POST /admin/parametrage/{setting}/upload
     *
     * Body: multipart/form-data with `file=<image>`.
     * Returns: { ok, url } or 422 with `error`.
     *
     * Only allowed for type=image_url (paint, branding, hero). Other types
     * fall through to the regular PUT JSON endpoint.
     */
    public function upload(Request $request, Setting $setting): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'edit:parametrage:*')) {
            return response()->json(['ok' => false, 'error' => 'Permission refusée.'], 403);
        }
        if ($setting->type !== 'image_url') {
            return response()->json([
                'ok'    => false,
                'error' => 'Ce paramètre n\'accepte pas d\'upload de fichier.',
            ], 422);
        }

        $data = Validator::make($request->all(), [
            // 4 MB max — generous for a logo / login bg but keeps the
            // homepage hero image snappy on mobile data.
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ])->validate();

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $ext  = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');

        // The Parametrage view composer expects a relative URL that
        // Laravel can render with the `public` disk's URL helper. We
        // generate a fresh ULID-prefixed filename so multiple uploads to
        // the same setting don't trample each other (we never delete the
        // old file in case it's referenced elsewhere — manual cleanup).
        $filename = strtolower(Str::ulid()->toBase32()) . '.' . $ext;
        $path     = "uploads/parametrage/{$filename}";

        $disk = $this->files->disk('public');
        $disk->putFileAs('uploads/parametrage', $file, $filename, ['visibility' => 'public']);

        // Store a RELATIVE URL — keeps the setting valid regardless of host
        // (localhost / 127.0.0.1:8000 / cuk.test / production). $disk->url()
        // would prepend APP_URL, which becomes wrong the moment the host or
        // port changes. A relative path resolves against the current request.
        $url = '/storage/' . $path;

        // Funnel through the same set() pipeline as a manual edit so the
        // change log, cache flush, and validation all fire correctly.
        $this->settings->set(
            key:       $setting->key,
            value:     $url,
            author:    $request->user(),
            ipAddress: (string) $request->ip(),
        );

        return response()->json([
            'ok'  => true,
            'key' => $setting->key,
            'url' => $url,
            'size_kb' => (int) round($file->getSize() / 1024),
        ]);
    }
}
