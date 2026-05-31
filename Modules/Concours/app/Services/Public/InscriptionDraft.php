<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Public;

use Illuminate\Contracts\Session\Session;

/**
 * Tiny wrapper around the inscription draft stored in the visitor's HTTP
 * session. Centralises the key so controllers / views don't string-type it
 * everywhere.
 *
 * The draft only holds *text* values — files (photo + documents) are never
 * persisted between steps. They're uploaded in one shot when the visitor
 * reaches the final step. That keeps disk usage and security simple at the
 * cost of "you can't close the browser between filling step 1 and uploading
 * documents". Given Gabonese mobile-data realities, this is the right
 * trade-off — we want the visitor to commit, not to dawdle.
 *
 * If we ever need cross-device resume, swap the session backend for a DB
 * row keyed by a short signed token mailed to the candidat.
 */
final class InscriptionDraft
{
    private const SESSION_KEY = 'concours.inscription.draft';
    private const CURRENT_STEP_KEY = 'concours.inscription.current_step';

    public function __construct(
        private readonly Session $session,
    ) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        $data = $this->session->get(self::SESSION_KEY, []);
        return is_array($data) ? $data : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @param array<string, mixed> $values */
    public function merge(array $values): void
    {
        $merged = array_replace($this->all(), $values);
        $this->session->put(self::SESSION_KEY, $merged);
    }

    public function currentStep(): ?string
    {
        $s = $this->session->get(self::CURRENT_STEP_KEY);
        return is_string($s) ? $s : null;
    }

    public function setCurrentStep(string $step): void
    {
        $this->session->put(self::CURRENT_STEP_KEY, $step);
    }

    public function reset(): void
    {
        $this->session->forget([self::SESSION_KEY, self::CURRENT_STEP_KEY]);
    }

    /**
     * Has the draft progressed at least up to the given step? Used to gate
     * direct GET access to later steps — a visitor cannot deep-link to
     * /inscription/documents without first completing the upstream steps.
     */
    public function hasReached(string $step, array $stepOrder): bool
    {
        $current = $this->currentStep();
        if ($current === null) {
            return $step === $stepOrder[0]; // only the first step is reachable from scratch
        }
        $currentIdx = array_search($current, $stepOrder, true);
        $askedIdx   = array_search($step, $stepOrder, true);
        if ($currentIdx === false || $askedIdx === false) {
            return false;
        }
        return $askedIdx <= ($currentIdx + 1); // can revisit any visited step + the next-to-fill one
    }
}
