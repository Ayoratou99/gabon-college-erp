<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Exceptions\InscriptionsClosedException;
use Modules\Concours\Http\Requests\RegisterCandidatRequest;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Services\CandidatRegistrationService;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

final class RegistrationController extends Controller
{
    public function __construct(
        private readonly CandidatRegistrationService $registration,
    ) {}

    public function showForm(): View|RedirectResponse
    {
        $session = ConcoursSession::active();
        if ($session === null || ! $session->isInscriptionOpen()) {
            return redirect()->route('concours.inscriptions.fermees');
        }

        return view('concours::public.registration.form', [
            'session'       => $session,
            'centres'       => $session->centres()->wherePivot('active', true)->orderBy('nom')->get(),
            'sections'      => Section::query()->where('ouvert_au_concours', true)->where('active', true)->orderBy('nom')->get(),
            'nationalites'  => Nationalite::query()->where('active', true)->orderBy('display_order')->orderBy('nom')->get(),
            'series'        => SerieBac::query()->where('active', true)->ordered()->get(),
            'documents'     => DocumentRequis::query()->where('active', true)->ordered()->get(),
        ]);
    }

    public function submit(RegisterCandidatRequest $request): RedirectResponse
    {
        try {
            $candidat = $this->registration->register($request->toDto());
        } catch (InscriptionsClosedException $e) {
            return back()->withInput()->withErrors(['inscription' => $e->getMessage()]);
        }

        return redirect()->route('concours.inscription.success', ['matricule' => $candidat->matricule_public]);
    }

    public function success(string $matricule): View
    {
        return view('concours::public.registration.success', [
            'matricule' => $matricule,
        ]);
    }

    public function closed(): View
    {
        return view('concours::public.registration.closed');
    }
}
