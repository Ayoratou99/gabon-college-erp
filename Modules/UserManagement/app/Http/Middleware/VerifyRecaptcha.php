<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Request;
use Modules\UserManagement\Exceptions\RecaptchaFailedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * reCAPTCHA v3 verification.
 *
 * The token comes from the `g-recaptcha-response` input or the
 * `X-Recaptcha-Token` header. We POST it to Google with our secret and
 * compare the returned score to the configured threshold.
 *
 * Bypassed entirely when reCAPTCHA is disabled in config (useful for
 * local dev without keys) and in the testing environment.
 */
final class VerifyRecaptcha
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('usermanagement.recaptcha.enabled') || app()->environment('testing')) {
            return $next($request);
        }

        $token = $request->input('g-recaptcha-response')
            ?? $request->header('X-Recaptcha-Token');

        if (! is_string($token) || $token === '') {
            throw new RecaptchaFailedException(reason: 'Jeton reCAPTCHA manquant.');
        }

        $response = $this->http
            ->asForm()
            ->timeout((int) config('usermanagement.recaptcha.timeout', 5))
            ->post(config('usermanagement.recaptcha.verify_url'), [
                'secret'   => config('usermanagement.recaptcha.secret'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

        if (! $response->ok()) {
            throw new RecaptchaFailedException(reason: 'Serveur reCAPTCHA inaccessible.');
        }

        $data = $response->json();
        $minScore = (float) config('usermanagement.recaptcha.min_score', 0.5);

        if (! ($data['success'] ?? false)) {
            throw new RecaptchaFailedException(reason: 'Jeton reCAPTCHA invalide.');
        }

        $score = (float) ($data['score'] ?? 0);
        if ($score < $minScore) {
            throw new RecaptchaFailedException(score: $score);
        }

        return $next($request);
    }
}
