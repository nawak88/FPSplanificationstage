<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuivi;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;
use Modules\RH\Models\Unite;

class PublicBesoinFormationPageService
{
    /** @return array<string, mixed> */
    public function form(
        ?User $user
    ): array {
        return [
            'stages' =>
                Stage::query()
                    ->where(
                        'actif',
                        true
                    )
                    ->orderBy(
                        'libelle_court'
                    )
                    ->get(),

            'unites' =>
                $this->uniteLabels(),

            'demandeur' =>
                $this->demandeurFor(
                    $user
                ),

            'planningUrl' =>
                $this->planningUrl(),
        ];
    }

    /** @return array<string, mixed> */
    public function confirmation(
        string $token
    ): array {
        $besoin =
            $this->findPublicBesoin(
                $token
            );

        return [
            'besoin' =>
                $besoin,

            'planningUrl' =>
                $this->planningUrl(),

            'suiviUrl' =>
                BesoinSuivi::getUrl(
                    [
                        'token' =>
                            $besoin->public_token,
                    ],
                    panel:
                        'fpsplanificationstage'
                ),
        ];
    }

    /** @return array<string, mixed> */
    public function suivi(
        string $token
    ): array {
        $besoin =
            $this->findPublicBesoin(
                $token
            );

        $besoin->load([
            'stage',
            'sessionStage.stage',
            'sessionStage.salle',
        ]);

        $statutPublic =
            match (
                $besoin->statut
            ) {
                'a_planifier' => [
                    'label' =>
                        'Demande reçue',

                    'description' =>
                        'Votre expression de besoin a bien été reçue et doit être étudiée par les gestionnaires.',

                    'type' =>
                        'info',
                ],

                'planifie' => [
                    'label' =>
                        'Session planifiée',

                    'description' =>
                        'Une session a été planifiée à partir de votre expression de besoin.',

                    'type' =>
                        'success',
                ],

                'conflit' => [
                    'label' =>
                        'En cours d’étude',

                    'description' =>
                        'La planification nécessite actuellement une étude complémentaire par les gestionnaires.',

                    'type' =>
                        'warning',
                ],

                'annule' => [
                    'label' =>
                        'Demande annulée',

                    'description' =>
                        'Cette expression de besoin est indiquée comme annulée.',

                    'type' =>
                        'danger',
                ],

                default => [
                    'label' =>
                        'En cours d’étude',

                    'description' =>
                        'Votre expression de besoin est en cours de traitement.',

                    'type' =>
                        'info',
                ],
            };

        return [
            'besoin' =>
                $besoin,

            'statutPublic' =>
                $statutPublic,

            'planningUrl' =>
                $this->planningUrl(),
        ];
    }

    /** @return array{planningUrl: string} */
    public function suiviRecherche(): array
    {
        return [
            'planningUrl' =>
                $this->planningUrl(),
        ];
    }

    private function findPublicBesoin(
        string $token
    ): BesoinFormation {
        return BesoinFormation::query()
            ->with('stage')
            ->where(
                'public_token',
                $token
            )
            ->where(
                'source',
                'portail'
            )
            ->firstOrFail();
    }

    /** @return array<int, string> */
    private function uniteLabels(): array
    {
        return Unite::query()
            ->whereNotNull(
                'libelle_long'
            )
            ->where(
                'libelle_long',
                '<>',
                ''
            )
            ->orderBy(
                'libelle_long'
            )
            ->pluck(
                'libelle_long'
            )
            ->unique()
            ->values()
            ->all();
    }

    private function demandeurFor(
        ?User $user
    ): ?string {
        if (! $user) {
            return null;
        }

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

        $unite =
            $marin?->unite
            ?? $this->findMindefUnite(
                data_get(
                    $user->getMindefConnectInformations(),
                    'main_department_number'
                )
            );

        return $unite?->libelle_long;
    }

    private function findMindefUnite(
        mixed $mindefUnite
    ): ?Unite {
        $mindefUnite = trim(
            (string) $mindefUnite
        );

        if ($mindefUnite === '') {
            return null;
        }

        return Unite::query()
            ->where(
                'libannudef',
                $mindefUnite
            )
            ->orWhere(
                'libelle_long',
                $mindefUnite
            )
            ->orWhere(
                'libelle_court',
                $mindefUnite
            )
            ->first();
    }

    private function planningUrl(): string
    {
        return PlanningFormations::getUrl(
            panel:
                'fpsplanificationstage'
        );
    }
}
