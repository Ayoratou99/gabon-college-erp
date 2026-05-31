<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Public;

use Illuminate\Contracts\Session\Session;

/**
 * Companion to InscriptionDraft — same shape, different session keys —
 * for the post-rejection modification wizard. Kept in its own service so
 * a visitor with an in-progress inscription draft can also start a
 * modification flow without the two stomping on each other's data.
 *
 * Files (photo + documents) are shared with the inscription flow via
 * InscriptionStagedDocuments: same on-disk visitor UUID, same staging
 * folder. Realistically a visitor is in one wizard at a time, and
 * replacing a doc in either flow does the same thing on disk anyway.
 */
final class ModificationDraft
{
    private const SESSION_KEY = 'concours.modify.draft';
    private const CURRENT_STEP_KEY = 'concours.modify.current_step';

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
     * @param  list<string>  $stepOrder
     */
    public function hasReached(string $step, array $stepOrder): bool
    {
        $current = $this->currentStep();
        if ($current === null) {
            return true; // modify wizard pre-fills from candidat, no gating needed
        }
        $currentIdx = array_search($current, $stepOrder, true);
        $askedIdx   = array_search($step, $stepOrder, true);
        if ($currentIdx === false || $askedIdx === false) {
            return false;
        }
        return $askedIdx <= ($currentIdx + 1);
    }
}
