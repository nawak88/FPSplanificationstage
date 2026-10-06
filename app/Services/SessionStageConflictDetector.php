<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Marin;

class SessionStageConflictDetector
{
    public function detect(
        array $data,
        ?int $currentSessionId = null
    ): array {
        $conflits = [];

        $debut = $this->toCarbon(
            $data['debut'] ?? null
        );

        $fin = $this->toCarbon(
            $data['fin'] ?? null
        );

        if (! $debut || ! $fin) {
            $conflits[] =
                'La date de début ou la date de fin est invalide.';

            return $conflits;
        }

        if ($fin->lessThanOrEqualTo($debut)) {
            $conflits[] =
                'La fin de la session doit être postérieure au début.';

            return $conflits;
        }

        $this->checkSalle(
            $data,
            $debut,
            $fin,
            $currentSessionId,
            $conflits
        );

        $this->checkInstructeurs(
            $data,
            $debut,
            $fin,
            $currentSessionId,
            $conflits
        );

        return array_values(
            array_unique($conflits)
        );
    }

    private function checkSalle(
        array $data,
        Carbon $debut,
        Carbon $fin,
        ?int $currentSessionId,
        array &$conflits
    ): void {
        $salleId = $data['salle_id'] ?? null;

        if (! $salleId) {
            return;
        }

        $salle = Salle::query()
            ->find($salleId);

        if (! $salle) {
            $conflits[] =
                'La salle sélectionnée est introuvable.';

            return;
        }

        /*
         * Vérification de capacité.
         */
        $capaciteMax = isset($data['capacite_max'])
            && $data['capacite_max'] !== ''
                ? (int) $data['capacite_max']
                : null;

        if (
            $capaciteMax !== null
            && $salle->capacite !== null
            && $capaciteMax > $salle->capacite
        ) {
            $conflits[] = sprintf(
                'La salle %s a une capacité de %d personnes, alors que la session prévoit jusqu’à %d personnes.',
                $salle->nom,
                $salle->capacite,
                $capaciteMax
            );
        }

        /*
         * Une salle est en conflit lorsque :
         *
         * session existante commence avant notre fin
         * ET
         * session existante finit après notre début.
         */
        $query = SessionStage::query()
            ->with('stage')
            ->where('salle_id', $salle->id)
            ->where('statut', '!=', 'annulee')
            ->where('debut', '<', $fin)
            ->where('fin', '>', $debut);

        if ($currentSessionId) {
            $query->whereKeyNot(
                $currentSessionId
            );
        }

        $sessions = $query->get();

        foreach ($sessions as $session) {
            $stageLabel =
                $session->stage?->libelle_court
                ?? 'Stage non renseigné';

            $conflits[] = sprintf(
                'Salle indisponible : %s est déjà utilisée par %s (%s) du %s au %s.',
                $salle->nom,
                $stageLabel,
                $session->code_session ?? 'session',
                $session->debut->format('d/m/Y H:i'),
                $session->fin->format('d/m/Y H:i')
            );
        }
    }

    private function checkInstructeurs(
        array $data,
        Carbon $debut,
        Carbon $fin,
        ?int $currentSessionId,
        array &$conflits
    ): void {
        $instructeurIds =
            $data['instructeurs'] ?? [];

        if (! is_array($instructeurIds)) {
            $instructeurIds = [];
        }

        $instructeurIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $instructeurIds
                    )
                )
            )
        );

        if ($instructeurIds === []) {
            return;
        }

        $instructeurs = Marin::query()
            ->whereIn('id', $instructeurIds)
            ->get()
            ->keyBy('id');

        foreach ($instructeurIds as $instructeurId) {
            $instructeur =
                $instructeurs->get(
                    $instructeurId
                );

            if (! $instructeur) {
                $conflits[] =
                    "L’formateur #{$instructeurId} est introuvable.";

                continue;
            }

            $nom = trim(
                mb_strtoupper($instructeur->nom)
                . ' '
                . $instructeur->prenom
            );

            /*
             * Recherche d'une autre session
             * utilisant déjà ce formateur.
             */
            $query = SessionStage::query()
                ->with('stage')
                ->where(
                    'statut',
                    '!=',
                    'annulee'
                )
                ->where(
                    'debut',
                    '<',
                    $fin
                )
                ->where(
                    'fin',
                    '>',
                    $debut
                )
                ->whereHas(
                    'instructeurs',
                    fn ($query) =>
                        $query->where(
                            'rh_marins.id',
                            $instructeurId
                        )
                );

            if ($currentSessionId) {
                $query->whereKeyNot(
                    $currentSessionId
                );
            }

            foreach ($query->get() as $session) {
                $stageLabel =
                    $session->stage?->libelle_court
                    ?? 'Stage non renseigné';

                $conflits[] = sprintf(
                    '%s est déjà affecté à %s (%s) du %s au %s.',
                    $nom,
                    $stageLabel,
                    $session->code_session ?? 'session',
                    $session->debut->format(
                        'd/m/Y H:i'
                    ),
                    $session->fin->format(
                        'd/m/Y H:i'
                    )
                );
            }

            /*
             * Recherche des indisponibilités
             * déclarées pour ce formateur.
             */
            $indisponibilites =
                IndisponibiliteInstructeur::query()
                    ->where(
                        'instructeur_id',
                        $instructeurId
                    )
                    ->where(
                        'actif',
                        true
                    )
                    ->whereDate(
                        'date_debut',
                        '<=',
                        $fin->toDateString()
                    )
                    ->whereDate(
                        'date_fin',
                        '>=',
                        $debut->toDateString()
                    )
                    ->get();

            foreach (
                $indisponibilites
                as $indisponibilite
            ) {
                [
                    $indispoDebut,
                    $indispoFin,
                ] = $this->indisponibiliteInterval(
                    $indisponibilite
                );

                if (
                    $debut->lt($indispoFin)
                    && $fin->gt($indispoDebut)
                ) {
                    $motif = $this->motifLabel(
                        $indisponibilite->motif
                    );

                    $conflits[] = sprintf(
                        '%s est indisponible du %s au %s%s.',
                        $nom,
                        $indispoDebut->format(
                            'd/m/Y H:i'
                        ),
                        $indispoFin->format(
                            'd/m/Y H:i'
                        ),
                        $motif
                            ? " — {$motif}"
                            : ''
                    );
                }
            }
        }
    }

    private function indisponibiliteInterval(
        IndisponibiliteInstructeur $indisponibilite
    ): array {
        $dateDebut =
            $indisponibilite
                ->date_debut
                ->format('Y-m-d');

        $dateFin =
            $indisponibilite
                ->date_fin
                ->format('Y-m-d');

        if (
            $indisponibilite
                ->journee_entiere
        ) {
            return [
                Carbon::parse(
                    $dateDebut
                )->startOfDay(),

                Carbon::parse(
                    $dateFin
                )->endOfDay(),
            ];
        }

        $heureDebut =
            $indisponibilite
                ->heure_debut
                ?: '00:00:00';

        $heureFin =
            $indisponibilite
                ->heure_fin
                ?: '23:59:59';

        return [
            Carbon::parse(
                $dateDebut
                . ' '
                . $heureDebut
            ),

            Carbon::parse(
                $dateFin
                . ' '
                . $heureFin
            ),
        ];
    }

    private function motifLabel(
        ?string $motif
    ): ?string {
        return match ($motif) {
            'conge' => 'Congé',
            'mission' => 'Mission',
            'formation' => 'Formation',
            'service' => 'Service',
            'absence' => 'Absence',
            'autre' => 'Autre',
            default => $motif,
        };
    }

    private function toCarbon(
        mixed $value
    ): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
