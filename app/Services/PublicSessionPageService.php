<?php

namespace Modules\FPSplanificationstage\Services;

use Modules\FPSplanificationstage\Filament\Pages\EspaceInstructeur;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Public\Pages\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;

class PublicSessionPageService
{
    /** @return array<string, mixed> */
    public function detail(
        SessionStage $session
    ): array {
        $session->load([
            'stage.prerequis' =>
                fn ($query) =>
                    $query
                        ->where(
                            'actif',
                            true
                        )
                        ->orderBy(
                            'ordre'
                        ),
            'salle',
        ]);

        $stage = $session->stage;

        if (! $stage) {
            abort(404);
        }

        $instructeur = app(InstructeurConnecteService::class)->resolve();
        $estInstructeurSession = $instructeur
            && $session->instructeurs()
                ->withoutGlobalScopes()
                ->where('rh_marins.id', $instructeur->getKey())
                ->exists();

        $stagiaires = $estInstructeurSession
            ? $session->inscriptions()
                ->with(['stagiaire.grade', 'stagiaire.unite'])
                ->whereNotIn('statut', ['refusee', 'annulee'])
                ->orderBy('candidat_nom')
                ->orderBy('candidat_prenom')
                ->get()
            : null;

        return [
            'stagiaires' => $stagiaires,
            'retourLabel' => $estInstructeurSession
                ? 'Retour à mon espace formateur'
                : 'Retour au planning des formations',

            'session' =>
                $session,

            'stage' =>
                $stage,

            'inscriptionUrl' =>
                Inscription::getUrl(
                    [
                        'session' =>
                            $session->getKey(),
                    ],
                    panel:
                        'fpsplanificationstage'
                ),

            'retourUrl' =>
                ($estInstructeurSession ? EspaceInstructeur::class : PlanningFormations::class)::getUrl(
                    panel:
                        'fpsplanificationstage'
                ),

            'inscriptionPossible' =>
                ! in_array(
                    $session->statut,
                    [
                        'annulee',
                        'terminee',
                    ],
                    true
                ),
        ];
    }
}
