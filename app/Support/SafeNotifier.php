<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Fail-soft notification delivery.
 *
 * A missing / misconfigured / unreachable mailer must NEVER block the action
 * that triggered the email. On shared hosting QUEUE_CONNECTION=sync makes even
 * ShouldQueue notifications run inline, so an SMTP error (no host, bad
 * credentials, refused connection…) would otherwise bubble up and break
 * registration, dossier validation, payment confirmation, etc.
 *
 * Every email send in the app goes through here: we catch any Throwable, log a
 * concise warning, and carry on. The business action succeeds; only the email
 * is lost (and recorded in the log so it can be chased up).
 */
final class SafeNotifier
{
    /**
     * On-demand notification to a single channel route (typically an email
     * address). No-ops on an empty route.
     */
    public static function route(string $channel, ?string $route, object $notification): void
    {
        $route = trim((string) $route);
        if ($route === '') {
            return;
        }

        try {
            Notification::route($channel, $route)->notify($notification);
        } catch (Throwable $e) {
            self::log($notification, $e, $route);
        }
    }

    /**
     * Notification to a Notifiable (User, …). Silently ignores a null target.
     */
    public static function send(?object $notifiable, object $notification): void
    {
        if ($notifiable === null) {
            return;
        }

        try {
            // @phpstan-ignore-next-line — Notifiables expose notify() via the trait.
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            self::log($notification, $e);
        }
    }

    private static function log(object $notification, Throwable $e, ?string $route = null): void
    {
        Log::warning('Notification non délivrée (non bloquant) — vérifiez la configuration MAIL_*.', [
            'notification' => $notification::class,
            'route'        => $route,
            'error'        => $e->getMessage(),
        ]);
    }
}
