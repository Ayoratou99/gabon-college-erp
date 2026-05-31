<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Ebilling;

use RuntimeException;

/**
 * Encrypts / decrypts the `external_reference` we send to eBilling so the
 * callback can verify a payload came from us without depending on a header
 * signature (eBilling does not send one).
 *
 * Scheme — AES-256-GCM, key from EBILLING_REFERENCE_KEY (32 raw bytes, base64
 * or hex). Output is base64url so it round-trips through any URL-safe field
 * eBilling's REST API accepts.
 *
 *   format on the wire:    base64url( IV(12) | TAG(16) | CIPHERTEXT(N) )
 *   payload (JSON):        { p: payment_uuid, c: candidat_uuid, n: nonce }
 *
 * If decryption fails (tampered, wrong key, garbage) decode() returns null.
 * The callback controller short-circuits with a 400 in that case — no DB
 * lookup, no audit row, no log noise.
 */
final class PaymentReferenceCipher
{
    /**
     * Build the wire reference for a freshly-created Payment row.
     *
     * @return string  base64url-encoded ciphertext, ~96 chars for this payload
     */
    public function encode(string $paymentId, string $candidatId): string
    {
        $payload = (string) json_encode([
            'p' => $paymentId,
            'c' => $candidatId,
            'n' => bin2hex(random_bytes(6)),  // nonce: identical inputs still produce different refs
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $iv  = random_bytes(12);  // GCM standard IV size
        $tag = '';
        $ct  = openssl_encrypt(
            $payload, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag,
        );
        if ($ct === false) {
            throw new RuntimeException('Unable to encrypt payment reference.');
        }

        return $this->base64UrlEncode($iv . $tag . $ct);
    }

    /**
     * Try to decode a reference received in an eBilling callback.
     *
     * @return null|array{p: string, c: string, n: string}  null when forged
     */
    public function decode(string $reference): ?array
    {
        try {
            $bin = $this->base64UrlDecode($reference);
        } catch (\Throwable) {
            return null;
        }

        if ($bin === null || strlen($bin) < 28) {
            return null;
        }

        $iv  = substr($bin, 0,  12);
        $tag = substr($bin, 12, 16);
        $ct  = substr($bin, 28);

        $plain = openssl_decrypt($ct, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            return null;
        }

        try {
            $payload = json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($payload)
            && isset($payload['p'], $payload['c'])
            && is_string($payload['p'])
            && is_string($payload['c'])
                ? $payload
                : null;
    }

    /**
     * The raw 32-byte key used for AES-256-GCM. Accepts either:
     *   - base64 (e.g. `base64:abcd…` like APP_KEY)
     *   - hex string (64 chars)
     *   - 32-byte raw string
     * Anything else throws; we'd rather fail loudly than silently use a
     * degraded key.
     */
    private function key(): string
    {
        $raw = (string) config('concours.ebilling.reference_key', '');
        if ($raw === '') {
            throw new RuntimeException(
                'EBILLING_REFERENCE_KEY is not configured. Generate one with: '
                . 'php -r "echo base64_encode(random_bytes(32));"',
            );
        }

        if (str_starts_with($raw, 'base64:')) {
            $key = base64_decode(substr($raw, 7), true);
            if ($key === false) {
                throw new RuntimeException('EBILLING_REFERENCE_KEY: invalid base64.');
            }
        } elseif (preg_match('/^[0-9a-f]{64}$/i', $raw) === 1) {
            $key = hex2bin($raw);
        } else {
            $key = $raw;
        }

        if (strlen($key) !== 32) {
            throw new RuntimeException(sprintf(
                'EBILLING_REFERENCE_KEY must decode to exactly 32 bytes (got %d).',
                strlen($key),
            ));
        }

        return $key;
    }

    private function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $s): ?string
    {
        $pad = strlen($s) % 4;
        if ($pad !== 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode(strtr($s, '-_', '+/'), true);
        return $bin === false ? null : $bin;
    }
}
