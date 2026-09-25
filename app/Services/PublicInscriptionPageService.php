<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\RH\Models\Brevet;
use Modules\RH\Models\Grade;
use Modules\RH\Models\Marin;
use Modules\RH\Models\Specialite;

class PublicInscriptionPageService
{
    /** @return array<string, mixed> */
    public function form(
        SessionStage $session,
        User $user
    ): array {
        $this->ensureSessionIsRegistrable(
            $session
        );

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

        return [
            'session' =>
                $session,

            'identity' =>
                $this->identityFor(
                    $user
                ),

            'grades' =>
                Grade::query()
                    ->orderBy('ordre')
                    ->orderBy('libelle_long')
                    ->get(),

            'specialites' =>
                Specialite::query()
                    ->orderBy('libelle_long')
                    ->get(),

            'brevets' =>
                Brevet::query()
                    ->orderBy('ordre')
                    ->orderBy('libelle_long')
                    ->get(),

            'sessionUrl' =>
                SessionDetail::getUrl(
                    [
                        'session' =>
                            $session->getKey(),
                    ],
                    panel:
                        'fpsplanificationstage'
                ),
        ];
    }

    /** @return array<string, mixed> */
    public function confirmation(
        string $code
    ): array {
        return [
            'inscription' =>
                $this->findByCode(
                    $code
                ),

            'planningUrl' =>
                PlanningFormations::getUrl(
                    panel:
                        'fpsplanificationstage'
                ),
        ];
    }

    public function findByCode(
        string $code
    ): Inscription {
        return Inscription::query()
            ->with([
                'sessionStage.stage',
            ])
            ->where(
                'code_inscription',
                $code
            )
            ->firstOrFail();
    }

    public function ensureSessionIsRegistrable(
        SessionStage $session
    ): void {
        if (
            in_array(
                $session->statut,
                [
                    'annulee',
                    'terminee',
                ],
                true
            )
        ) {
            abort(404);
        }
    }

    /** @return array<string, ?string> */
    private function identityFor(
        User $user
    ): array {
        $marin =
            Marin::fromUser(
                $user
            )
            ?? app(
                StagiaireResolver::class
            )->find([
                'nom' =>
                    $user->nom,

                'prenom' =>
                    $user->prenom,

                'email' =>
                    $user->email,
            ]);

        $mindef =
            $user
                ->getMindefConnectInformations();

        return [
            'nom' =>
                $user->nom,

            'prenom' =>
                $user->prenom,

            'email' =>
                $user->email,

            'matricule' =>
                $marin?->matricule,

            'nid' =>
                $marin?->nid,

            'grade' =>
                $marin
                    ?->grade
                    ?->libelle_court
                ?? data_get(
                    $mindef,
                    'short_rank'
                ),

            'brevet' =>
                $marin
                    ?->brevet
                    ?->libelle_court,

            'specialite' =>
                $marin
                    ?->specialite
                    ?->libelle_court,

            'unite' =>
                $marin
                    ?->unite
                    ?->libelle_court
                ?? data_get(
                    $mindef,
                    'main_department_number'
                ),
        ];
    }
}
