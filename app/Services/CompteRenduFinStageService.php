<?php

namespace Modules\FPSplanificationstage\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;

class CompteRenduFinStageService
{
    public const SEUIL_VALIDATION = 12;

    public function session(int|string $sessionId): SessionStage
    {
        return app(SessionInstructeurPageService::class)->session($sessionId);
    }

    public function peutSaisir(SessionStage $session): bool
    {
        return $session->statut !== 'annulee'
            && $session->fin !== null
            && $session->fin->lte(now());
    }

    public function inscription(int|string $sessionId, int|string $inscriptionId): Inscription
    {
        $session = $this->session($sessionId);
        abort_unless($this->peutSaisir($session), 403);

        return $session->inscriptions()
            ->whereNotIn('statut', ['refusee', 'annulee'])
            ->findOrFail($inscriptionId);
    }

    public function enregistrer(int|string $sessionId, int|string $inscriptionId, array $data): void
    {
        $inscription = $this->inscription($sessionId, $inscriptionId);
        $automatique = is_numeric($data['note_fin_stage'] ?? null)
            && (float) $data['note_fin_stage'] >= self::SEUIL_VALIDATION;

        $valide = Validator::make($data, [
            'note_fin_stage' => ['required', 'numeric', 'between:0,20', 'decimal:0,2'],
            'stage_valide' => [Rule::requiredIf(! $automatique), 'nullable', 'boolean'],
            'date_attribution' => ['required', 'date', 'after_or_equal:' . $inscription->sessionStage->fin->toDateString(), 'before_or_equal:today'],
            'observations_fin_stage' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        // Le résultat est distinct du statut administratif de l'inscription.
        $inscription->forceFill([
            'note_fin_stage' => $valide['note_fin_stage'],
            'stage_valide' => $automatique || (bool) ($valide['stage_valide'] ?? false),
            'date_attribution' => $valide['date_attribution'],
            'observations_fin_stage' => $valide['observations_fin_stage'] ?? null,
        ])->saveQuietly();
    }

    public function resultat(Inscription $inscription): string
    {
        if ($inscription->note_fin_stage === null || $inscription->stage_valide === null) {
            return 'Non renseigné';
        }

        if ((float) $inscription->note_fin_stage >= self::SEUIL_VALIDATION) {
            return 'Validé';
        }

        $note = rtrim(rtrim(number_format((float) $inscription->note_fin_stage, 2, ',', ''), '0'), ',');

        return $note . '/20 — ' . ($inscription->stage_valide ? 'Validé' : 'Non validé');
    }

    public function donneesPdf(int|string $sessionId): array
    {
        $data = app(SessionInstructeurPageService::class)->detail($sessionId);
        abort_unless($this->peutSaisir($data['session']), 403);

        if ($data['stagiaires']->isEmpty() || $data['stagiaires']->contains(
            fn (Inscription $inscription): bool => $inscription->note_fin_stage === null
                || $inscription->stage_valide === null || $inscription->date_attribution === null
        )) {
            throw ValidationException::withMessages([
                'resultats' => 'Renseignez le résultat et la date d’attribution de chaque stagiaire avant de télécharger le compte rendu.',
            ]);
        }

        $data['formateurs'] = $data['session']->instructeurs()
            ->withoutGlobalScopes()->with('grade')->get();

        return $data;
    }

    public function render(int|string $sessionId): string
    {
        $data = $this->donneesPdf($sessionId);
        $data['resultats'] = $data['stagiaires']->mapWithKeys(
            fn (Inscription $inscription): array => [$inscription->getKey() => $this->resultat($inscription)]
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('fpsplanificationstage::public.compte-rendu-fin-stage-pdf', $data)->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
