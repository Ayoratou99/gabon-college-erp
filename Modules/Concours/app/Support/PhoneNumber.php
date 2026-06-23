<?php

declare(strict_types=1);

namespace Modules\Concours\Support;

/**
 * Canonical handling for candidat phone numbers.
 *
 * Storage form is digits only, with an optional single leading "+". Users
 * routinely type separators ("066-22-88-77", "066 22 88 77", "066.22.88.77")
 * — those are stripped so the value is consistent everywhere (display,
 * dedup / uniqueness checks, lookups).
 */
final class PhoneNumber
{
    /** Validation pattern for a NORMALISED number: optional "+", then 6–20 digits. */
    public const REGEX = '/^\+?[0-9]{6,20}$/';

    /**
     * Strip every non-digit except a single leading "+":
     *   "066-22-88-77"      → "066228877"
     *   "+241 01 23 45 67"  → "+24101234567"
     *   "  06.62.28.87  "   → "06622887"
     * Garbage with no digits (e.g. an email mistyped here) collapses to "".
     */
    public static function normalize(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $plus   = str_starts_with($raw, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits === '' ? '' : $plus . $digits;
    }
}
