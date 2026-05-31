<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Subfolder-aware media URL resolver.
 *
 * The app stores image references (brand logo, banner, login background,
 * parametrage uploads) as domain-root-absolute paths like "/img/cuk/logo.jpg"
 * or "/storage/uploads/x.png". Those only resolve when the app is served from
 * a domain ROOT. When the app lives in a sub-path (e.g.
 * https://host/concours-…/), a literal "/img/…" points at the domain root and
 * 404s.
 *
 * Media::url() routes local paths through Laravel's asset(), which honours
 * ASSET_URL (set to the sub-path in production). External URLs and data URIs
 * are returned untouched.
 *
 *   Media::url('/img/cuk/logo.jpg')            → {ASSET_URL}/img/cuk/logo.jpg
 *   Media::url('/storage/uploads/x.png')       → {ASSET_URL}/storage/uploads/x.png
 *   Media::url('https://cdn.example/x.jpg')    → https://cdn.example/x.jpg (as-is)
 *   Media::url(null)                            → ''
 */
final class Media
{
    public static function url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        // Leave absolute URLs / protocol-relative / data URIs untouched.
        if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
            return $path;
        }

        // Local path → through asset() so it respects ASSET_URL (sub-path aware).
        return asset(ltrim($path, '/'));
    }
}
