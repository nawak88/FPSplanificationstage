<?php

namespace Modules\FPSplanificationstage\Services;

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

        return [
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
                PlanningFormations::getUrl(
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
