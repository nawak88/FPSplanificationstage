<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Throwable;

class BesoinFormationGroupedPlanner
{
    /**
     * Planifie un besoin seul ou une sélection de besoins.
     *
     * Règles :
     * - même stage = regroupement possible ;
     * - on cherche les dates où le plus de besoins sont compatibles ;
     * - l'effectif prévisionnel sert à calculer le nombre de sessions ;
     * - la capacité réelle de chaque session reste celle du catalogue ;
     * - un besoin peut être réparti sur plusieurs sessions ;
     * - une session peut couvrir plusieurs besoins ;
     * - samedi et dimanche ne comptent pas dans la durée ;
     * - les conflits salle / instructeurs restent contrôlés par
     *   SessionStageConflictDetector.
     */
    public function plan(
        BesoinFormation|Collection|EloquentCollection $input,
        bool $allowBelowMinimum = false
    ): array {
        /*
         * PLANIFICATION_FORCE_SOUS_MINIMUM_V1
         *
         * Le minimum d'effectif est une contrainte métier souple :
         * l'utilisateur peut explicitement décider de programmer
         * une session en dessous du minimum.
         *
         * Les conflits de salle / instructeur et la durée du stage
         * restent, eux, bloquants.
         */
        $records =
            $input instanceof BesoinFormation
                ? collect([$input])
                : collect($input);

        $ids =
            $records
                ->pluck('id')
                ->filter()
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->unique()
                ->values();

        if ($ids->isEmpty()) {
            return $this->resultatVide(
                'Aucun besoin n’a été sélectionné.'
            );
        }

        /*
         * On recharge depuis la base pour travailler sur l'état réel
         * au moment du clic.
         */
        $besoins =
            BesoinFormation::query()
                ->with('stage')
                ->whereIn(
                    'id',
                    $ids->all()
                )
                ->get();

        $skipped = 0;
        $failed = 0;
        $failedCodes = [];
        $messages = [];
        $sessionsCreated = 0;

        /*
         * Ne sont traités que les besoins encore opérationnels.
         */
        $traitables =
            $besoins
                ->filter(
                    function (
                        BesoinFormation $besoin
                    ) use (
                        &$skipped,
                        &$failed,
                        &$failedCodes,
                        &$messages
                    ): bool {
                        if (
                            $besoin->statut
                            === 'annule'
                        ) {
                            $skipped++;
                            return false;
                        }

                        if (
                            ! in_array(
                                $besoin->statut,
                                [
                                    'a_planifier',
                                    'partiellement_planifie',
                                    'conflit',
                                ],
                                true
                            )
                        ) {
                            $skipped++;
                            return false;
                        }

                        if (
                            $besoin
                                ->nombre_stagiaires
                            === null
                        ) {
                            $failed++;
                            $failedCodes[] =
                                $besoin
                                    ->code_besoin;

                            $messages[] =
                                $besoin
                                    ->code_besoin
                                . ' : effectif prévisionnel non renseigné.';

                            return false;
                        }

                        if (
                            $besoin
                                ->effectif_restant
                            <= 0
                        ) {
                            app(
                                BesoinSessionAllocationService::class
                            )
                                ->synchroniserStatut(
                                    $besoin
                                );

                            $skipped++;
                            return false;
                        }

                        /* VALIDATION_PERIODE_AVANT_PLANIFICATION_V1_2 */
                        $periodeError =
                            \Modules\FPSplanificationstage\Services\BesoinPeriodeService::validateBesoin(
                                $besoin
                            );

                        if ($periodeError !== null) {
                            $failed++;
                            $failedCodes[] = $besoin->code_besoin;
                            $messages[] =
                                $besoin->code_besoin
                                . ' — '
                                . ($besoin->stage?->libelle_court ?? 'Stage')
                                . ' : '
                                . $periodeError;

                            return false;
                        }

                        return true;
                    }
                )
                ->values();

        /*
         * Les stages différents sont indépendants.
         */
        foreach (
            $traitables
                ->groupBy(
                    'stage_id'
                )
            as $stageId =>
                $groupe
        ) {
            try {
                $resultatStage =
                    $this->planifierStage(
                        (int) $stageId,
                        $groupe,
                        $allowBelowMinimum
                    );

                $sessionsCreated +=
                    $resultatStage[
                        'sessions_created'
                    ];

                $messages =
                    array_merge(
                        $messages,
                        $resultatStage[
                            'messages'
                        ]
                    );

                $failed +=
                    $resultatStage[
                        'failed'
                    ];

                $failedCodes =
                    array_merge(
                        $failedCodes,
                        $resultatStage[
                            'failed_codes'
                        ]
                    );
            } catch (Throwable $exception) {
                $failed +=
                    $groupe
                        ->count();

                foreach (
                    $groupe
                    as $besoin
                ) {
                    $failedCodes[] =
                        $besoin
                            ->code_besoin;
                }

                $messages[] =
                    sprintf(
                        'Stage ID %d : %s',
                        (int) $stageId,
                        $exception
                            ->getMessage()
                    );
            }
        }

        /*
         * État final des besoins sélectionnés.
         */
        $finaux =
            BesoinFormation::query()
                ->whereIn(
                    'id',
                    $ids->all()
                )
                ->get();

        $fullyPlanned =
            $finaux
                ->filter(
                    fn (
                        BesoinFormation $besoin
                    ): bool =>
                        $besoin->statut
                        === 'planifie'
                )
                ->count();

        $partial =
            $finaux
                ->filter(
                    fn (
                        BesoinFormation $besoin
                    ): bool =>
                        $besoin->statut
                        === 'partiellement_planifie'
                )
                ->count();

        $stillToPlan =
            $finaux
                ->filter(
                    fn (
                        BesoinFormation $besoin
                    ): bool =>
                        in_array(
                            $besoin->statut,
                            [
                                'a_planifier',
                                'conflit',
                            ],
                            true
                        )
                        && $besoin
                            ->statut
                        !== 'annule'
                )
                ->count();

        $unresolved =
            $partial
            + $stillToPlan;

        $message =
            sprintf(
                '%d session(s) créée(s) • %d besoin(s) totalement couvert(s) • %d partiellement couvert(s) • %d restant à planifier.',
                $sessionsCreated,
                $fullyPlanned,
                $partial,
                $stillToPlan
            );

        if ($messages !== []) {
            $message .=
                "\n\n"
                . implode(
                    "\n",
                    array_slice(
                        array_values(
                            array_unique(
                                $messages
                            )
                        ),
                        0,
                        12
                    )
                );
        }

        return [
            /*
             * Compatibilité avec l'action unitaire actuelle.
             */
            'success' =>
                $sessionsCreated > 0,

            'message' =>
                $message,

            'suggestions' =>
                '',

            /*
             * Compatibilité avec l'action de masse actuelle.
             */
            'planned' =>
                $fullyPlanned,

            'conflicts' =>
                $unresolved,

            'skipped' =>
                $skipped,

            'failed' =>
                $failed,

            'failed_codes' =>
                array_values(
                    array_unique(
                        $failedCodes
                    )
                ),

            /*
             * Informations supplémentaires.
             */
            'sessions_created' =>
                $sessionsCreated,

            'partial' =>
                $partial,

            'still_to_plan' =>
                $stillToPlan,

            'conflict_messages' =>
                array_values(
                    array_unique(
                        $messages
                    )
                ),
        ];
    }

    private function planifierStage(
        int $stageId,
        Collection $besoins,
        bool $allowBelowMinimum = false
    ): array {
        $stage =
            Stage::query()
                ->find(
                    $stageId
                );

        if (! $stage) {
            return $this->echecGroupe(
                $besoins,
                'Stage introuvable.'
            );
        }

        $duree =
            (float) (
                $stage
                    ->duree_jours
                ?? 0
            );

        if ($duree <= 0) {
            return $this->echecGroupe(
                $besoins,
                sprintf(
                    '%s : durée non renseignée dans le catalogue.',
                    $stage
                        ->libelle_court
                )
            );
        }

        $capacite =
            (int) (
                $stage
                    ->capacite_max
                ?? 0
            );

        if ($capacite <= 0) {
            return $this->echecGroupe(
                $besoins,
                sprintf(
                    '%s : capacité maximale non renseignée.',
                    $stage
                        ->libelle_court
                )
            );
        }

        $capaciteMin =
            max(
                0,
                (int) (
                    $stage
                        ->capacite_min
                    ?? 0
                )
            );

        /*
         * Tous les instructeurs actifs associés au stage
         * sont repris comme dans le moteur historique.
         */
        $instructeurIds =
            $stage
                ->instructeurs()
                ->wherePivot(
                    'actif',
                    true
                )
                ->pluck(
                    'users.id'
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->all();

        if ($instructeurIds === []) {
            return $this->echecGroupe(
                $besoins,
                sprintf(
                    '%s : aucun instructeur actif associé.',
                    $stage
                        ->libelle_court
                )
            );
        }

        /*
         * Les préférences de salle ne font pas partie du référentiel
         * RH. La salle préférentielle du stage reste prioritaire.
         */
        $sallesInstructeurs = [];

        /*
         * Comme décidé précédemment, la salle est dimensionnée
         * pour la capacité maximale DU STAGE, pas pour l'effectif
         * prévisionnel actuellement affecté.
         */
        $salles =
            Salle::query()
                ->where(
                    'actif',
                    true
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $capacite
                    ): void {
                        $query
                            ->whereNull(
                                'capacite'
                            )
                            ->orWhere(
                                'capacite',
                                '>=',
                                $capacite
                            );
                    }
                )
                ->get()
                ->sortBy(
                    function (
                        Salle $salle
                    ) use (
                        $stage,
                        $sallesInstructeurs
                    ): string {
                        $score =
                            $this
                                ->roomPreferenceScore(
                                    $salle,
                                    $stage,
                                    $sallesInstructeurs
                                );

                        return sprintf(
                            '%02d-%s',
                            $score,
                            mb_strtolower(
                                (string)
                                    $salle
                                        ->nom
                            )
                        );
                    }
                )
                ->values();

        if ($salles->isEmpty()) {
            return $this->echecGroupe(
                $besoins,
                sprintf(
                    '%s : aucune salle active ne peut accueillir %d personnes.',
                    $stage
                        ->libelle_court,
                    $capacite
                )
            );
        }

        $sessionsCreated = 0;
        $messages = [];
        $failed = 0;
        $failedCodes = [];

        /*
         * Sécurité contre une boucle anormale.
         * 50 sessions dans un seul clic constitue déjà une très
         * grosse planification.
         */
        for (
            $iteration = 0;
            $iteration < 50;
            $iteration++
        ) {
            $courants =
                BesoinFormation::query()
                    ->whereIn(
                        'id',
                        $besoins
                            ->pluck(
                                'id'
                            )
                            ->all()
                    )
                    ->get()
                    ->filter(
                        fn (
                            BesoinFormation $besoin
                        ): bool =>
                            $besoin
                                ->effectif_restant
                            > 0
                            && $besoin
                                ->statut
                            !== 'annule'
                    )
                    ->values();

            if ($courants->isEmpty()) {
                break;
            }

            $candidats =
                $this->candidateDates(
                    $courants,
                    $duree,
                    $capacite,
                    $capaciteMin,
                    $allowBelowMinimum
                );

            if ($candidats === []) {
                $messages[] =
                    sprintf(
                        '%s : aucun créneau compatible avec la durée du stage et les dates demandées ne permet de couvrir les effectifs restants.',
                        $stage
                            ->libelle_court
                    );

                break;
            }

            $trouve =
                false;

            $allConflicts = [];

            foreach (
                $candidats
                as $candidat
            ) {
                $debut =
                    $candidat[
                        'debut'
                    ];

                $fin =
                    $candidat[
                        'fin'
                    ];

                $eligibles =
                    $this->eligibleNeeds(
                        $courants,
                        $debut,
                        $fin
                    );

                if ($eligibles->isEmpty()) {
                    continue;
                }


                foreach (
                    $salles
                    as $salle
                ) {
                    $data = [
                        'stage_id' =>
                            $stage->id,

                        'debut' =>
                            $debut
                                ->format(
                                    'Y-m-d H:i:s'
                                ),

                        'fin' =>
                            $fin
                                ->format(
                                    'Y-m-d H:i:s'
                                ),

                        'salle_id' =>
                            $salle->id,

                        'capacite_min' =>
                            $stage
                                ->capacite_min,

                        'capacite_max' =>
                            $stage
                                ->capacite_max,

                        'instructeurs' =>
                            $instructeurIds,
                    ];

                    $conflicts =
                        app(
                            SessionStageConflictDetector::class
                        )
                            ->detect(
                                $data
                            );

                    if ($conflicts !== []) {
                        $allConflicts =
                            array_merge(
                                $allConflicts,
                                $conflicts
                            );

                        continue;
                    }

                    $creation =
                        $this->creerSessionEtAffecter(
                            $stage,
                            $salle,
                            $instructeurIds,
                            $debut,
                            $fin,
                            $eligibles,
                            $capacite
                        );

                    if (
                        $creation[
                            'effectif_affecte'
                        ]
                        <= 0
                    ) {
                        continue;
                    }

                    $sessionsCreated++;

                    $messages[] =
                        $creation[
                            'message'
                        ];

                    $trouve =
                        true;

                    break 2;
                }
            }

            if (! $trouve) {
                $allConflicts =
                    array_values(
                        array_unique(
                            $allConflicts
                        )
                    );

                $messages[] =
                    sprintf(
                        '%s : aucun créneau libre trouvé pour les besoins restants.%s',
                        $stage
                            ->libelle_court,
                        $allConflicts !== []
                            ? ' '
                                . implode(
                                    ' | ',
                                    array_slice(
                                        $allConflicts,
                                        0,
                                        3
                                    )
                                )
                            : ''
                    );

                /*
                 * Les besoins restent à planifier / partiels.
                 * On ne les force pas au statut Conflit ici, car certains
                 * ont peut-être déjà une session correctement planifiée.
                 */
                break;
            }
        }

        if ($sessionsCreated >= 50) {
            $messages[] =
                sprintf(
                    '%s : limite de sécurité de 50 sessions atteinte.',
                    $stage
                        ->libelle_court
                );
        }

        return [
            'sessions_created' =>
                $sessionsCreated,

            'messages' =>
                $messages,

            'failed' =>
                $failed,

            'failed_codes' =>
                $failedCodes,
        ];
    }

    /**
     * Construit et classe toutes les dates de début possibles.
     *
     * Priorité :
     * 1. remplir au maximum une session ;
     * 2. respecter d'abord les demandes à date fixe ;
     * 3. fusionner le plus grand nombre de bâtiments/unités ;
     * 4. tenir compte des priorités métier ;
     * 5. à égalité, choisir la date la plus proche.
     */
    private function candidateDates(
        Collection $besoins,
        float $duree,
        int $capacite,
        int $capaciteMin,
        bool $allowBelowMinimum = false
    ): array {
        /*
         * BESOIN_PERIODES_MOTEUR_V1_2
         * EFFECTIF_NE_BLOQUE_JAMAIS_V1_2
         *
         * $capaciteMin et $allowBelowMinimum sont conservés uniquement pour
         * compatibilité avec les appels installés précédemment. Ils ne
         * filtrent plus les candidats : un besoin de 1 personne peut ouvrir
         * une session à la capacité maximale du stage.
         */
        $starts = [];
        $ends = [];

        foreach ($besoins as $besoin) {
            if (! $besoin->date_debut_souhaitee) {
                continue;
            }

            if ($besoin->type_periode === BesoinPeriodeService::TYPE_INDISPONIBILITE) {
                if ($besoin->date_fin_souhaitee) {
                    [$start, $end] = BesoinPeriodeService::unavailableSearchBounds($besoin);
                    $starts[] = $start;
                    $ends[] = $end;
                }

                continue;
            }

            $start = Carbon::parse($besoin->date_debut_souhaitee)->startOfDay();
            $starts[] = $start;

            if (
                in_array(
                    $besoin->type_periode,
                    [
                        'plage',
                        'plage_demarrage',
                    ],
                    true
                )
            ) {
                if (! $besoin->date_fin_souhaitee) {
                    continue;
                }

                $ends[] = Carbon::parse($besoin->date_fin_souhaitee)->endOfDay();
            } else {
                $ends[] = $start->copy()->endOfDay();
            }
        }

        if ($starts === [] || $ends === []) {
            return [];
        }

        $minStart = collect($starts)
            ->sortBy(fn (Carbon $date): int => $date->getTimestamp())
            ->first()
            ->copy();

        $maxEnd = collect($ends)
            ->sortByDesc(fn (Carbon $date): int => $date->getTimestamp())
            ->first()
            ->copy();

        if ($minStart->diffInDays($maxEnd) > 1095) {
            $maxEnd = $minStart->copy()->addDays(1095);
        }

        $candidats = [];
        $cursor = $minStart->copy();

        while ($cursor->lte($maxEnd)) {
            if ($cursor->isWeekend()) {
                $cursor->addDay();
                continue;
            }

            [$debut, $fin] = $this->buildPeriodFromDuration($cursor, $duree);
            $eligibles = $this->eligibleNeeds($besoins, $debut, $fin);

            if (! $eligibles->isEmpty()) {
                $total = (int) $eligibles->sum(
                    fn (BesoinFormation $besoin): int =>
                        $besoin->effectif_restant
                );

                /*
                 * Aucun seuil minimum ici : la capacité réelle de la session
                 * reste $capacite, mais l'allocation initiale peut n'être que 1.
                 */
                $fixedDemand = (int) $eligibles
                    ->filter(
                        fn (BesoinFormation $besoin): bool =>
                            $besoin->type_periode === 'dates_fixes'
                    )
                    ->sum(
                        fn (BesoinFormation $besoin): int =>
                            min($capacite, $besoin->effectif_restant)
                    );

                $priorityScore = (int) $eligibles->sum(
                    fn (BesoinFormation $besoin): int =>
                        $this->priorityWeight($besoin->priorite)
                );

                $candidats[] = [
                    'debut' => $debut,
                    'fin' => $fin,
                    'coverage' => min($capacite, $total),
                    'fixed_demand' => min($capacite, $fixedDemand),
                    'need_count' => $eligibles->count(),
                    'priority_score' => $priorityScore,
                ];
            }

            $cursor->addDay();
        }

        usort(
            $candidats,
            function (array $a, array $b): int {
                foreach (
                    [
                        'coverage',
                        'fixed_demand',
                        'need_count',
                        'priority_score',
                    ] as $key
                ) {
                    if ($a[$key] !== $b[$key]) {
                        return $b[$key] <=> $a[$key];
                    }
                }

                return $a['debut']->getTimestamp()
                    <=> $b['debut']->getTimestamp();
            }
        );

        return $candidats;
    }


    private function eligibleNeeds(
        Collection $besoins,
        Carbon $debut,
        Carbon $fin
    ): Collection {
        return $besoins
            ->filter(
                function (BesoinFormation $besoin) use ($debut, $fin): bool {
                    if ($besoin->effectif_restant <= 0) {
                        return false;
                    }

                    if (! $besoin->date_debut_souhaitee) {
                        return false;
                    }

                    $souhaitee = Carbon::parse(
                        $besoin->date_debut_souhaitee
                    )->startOfDay();

                    if ($besoin->type_periode === 'dates_fixes') {
                        return $debut->isSameDay($souhaitee);
                    }

                    if ($besoin->type_periode === BesoinPeriodeService::TYPE_INDISPONIBILITE) {
                        return BesoinPeriodeService::avoidsUnavailablePeriod($besoin, $debut, $fin);
                    }

                    if (
                        ! in_array(
                            $besoin->type_periode,
                            [
                                'plage',
                                'plage_demarrage',
                            ],
                            true
                        )
                        || ! $besoin->date_fin_souhaitee
                    ) {
                        return false;
                    }

                    $finSouhaitee = Carbon::parse(
                        $besoin->date_fin_souhaitee
                    )->endOfDay();

                    if ($besoin->type_periode === 'plage_demarrage') {
                        /*
                         * Seul le début doit se trouver dans la plage.
                         * La fin réelle du stage peut dépasser cette fenêtre.
                         */
                        return $debut->gte($souhaitee)
                            && $debut->lte($finSouhaitee);
                    }

                    /*
                     * Plage de disponibilité : le stage complet doit tenir
                     * entre le début et la fin de disponibilité.
                     */
                    return $debut->gte($souhaitee)
                        && $fin->lte($finSouhaitee);
                }
            )
            ->sort(
                function (BesoinFormation $a, BesoinFormation $b): int {
                    $fixeA = $a->type_periode === 'dates_fixes' ? 1 : 0;
                    $fixeB = $b->type_periode === 'dates_fixes' ? 1 : 0;

                    if ($fixeA !== $fixeB) {
                        return $fixeB <=> $fixeA;
                    }

                    $prioriteA = $this->priorityWeight($a->priorite);
                    $prioriteB = $this->priorityWeight($b->priorite);

                    if ($prioriteA !== $prioriteB) {
                        return $prioriteB <=> $prioriteA;
                    }

                    $finA = $a->date_fin_souhaitee
                        ? Carbon::parse($a->date_fin_souhaitee)->getTimestamp()
                        : Carbon::parse($a->date_debut_souhaitee)->getTimestamp();

                    $finB = $b->date_fin_souhaitee
                        ? Carbon::parse($b->date_fin_souhaitee)->getTimestamp()
                        : Carbon::parse($b->date_debut_souhaitee)->getTimestamp();

                    if ($finA !== $finB) {
                        return $finA <=> $finB;
                    }

                    return $a->id <=> $b->id;
                }
            )
            ->values();
    }


    private function creerSessionEtAffecter(
        Stage $stage,
        Salle $salle,
        array $instructeurIds,
        Carbon $debut,
        Carbon $fin,
        Collection $eligibles,
        int $capacite
    ): array {
        return DB::transaction(
            function () use (
                $stage,
                $salle,
                $instructeurIds,
                $debut,
                $fin,
                $eligibles,
                $capacite
            ): array {
                /*
                 * On recharge et verrouille les besoins pour éviter
                 * qu'un second clic simultané affecte les mêmes effectifs.
                 */
                $locked =
                    BesoinFormation::query()
                        ->whereIn(
                            'id',
                            $eligibles
                                ->pluck(
                                    'id'
                                )
                                ->all()
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy(
                            'id'
                        );

                $allocations = [];
                $placesARepartir =
                    $capacite;

                foreach (
                    $eligibles
                    as $besoin
                ) {
                    if ($placesARepartir <= 0) {
                        break;
                    }

                    $courant =
                        $locked
                            ->get(
                                $besoin->id
                            );

                    if (! $courant) {
                        continue;
                    }

                    $reste =
                        $courant
                            ->effectif_restant;

                    if ($reste <= 0) {
                        continue;
                    }

                    $affecte =
                        min(
                            $reste,
                            $placesARepartir
                        );

                    if ($affecte <= 0) {
                        continue;
                    }

                    $allocations[
                        $courant->id
                    ] =
                        $affecte;

                    $placesARepartir -=
                        $affecte;
                }

                if ($allocations === []) {
                    return [
                        'effectif_affecte' =>
                            0,

                        'message' =>
                            'Aucune allocation à créer.',
                    ];
                }

                $codes =
                    BesoinFormation::query()
                        ->whereIn(
                            'id',
                            array_keys(
                                $allocations
                            )
                        )
                        ->get()
                        ->map(
                            function (
                                BesoinFormation $besoin
                            ) use (
                                $allocations
                            ): string {
                                return sprintf(
                                    '%s %s=%d',
                                    $besoin
                                        ->code_besoin,
                                    $besoin
                                        ->demandeur,
                                    $allocations[
                                        $besoin->id
                                    ]
                                );
                            }
                        )
                        ->implode(
                            ' ; '
                        );

                $session =
                    SessionStage::create([
                        'stage_id' =>
                            $stage->id,

                        'salle_id' =>
                            $salle->id,

                        'debut' =>
                            $debut,

                        'fin' =>
                            $fin,

                        'capacite_min' =>
                            $stage
                                ->capacite_min,

                        'capacite_max' =>
                            $stage
                                ->capacite_max,

                        'statut' =>
                            'planifiee',

                        'salle_forcee' =>
                            false,

                        'source' =>
                            'besoin',

                        'commentaire' =>
                            'Fusion automatique des expressions de besoin — '
                            . $codes,
                    ]);

                $session
                    ->instructeurs()
                    ->sync(
                        $instructeurIds
                    );

                foreach (
                    $allocations
                    as $besoinId =>
                        $effectif
                ) {
                    $besoin =
                        $locked
                            ->get(
                                $besoinId
                            );

                    $besoin
                        ->sessions()
                        ->syncWithoutDetaching([
                            $session->id => [
                                'effectif_prevu' =>
                                    $effectif,
                            ],
                        ]);

                    /*
                     * Compatibilité transitoire avec l'ancien modèle.
                     * La première session reste aussi mémorisée dans
                     * session_stage_id.
                     */
                    if (
                        $besoin
                            ->session_stage_id
                        === null
                    ) {
                        $besoin
                            ->forceFill([
                                'session_stage_id' =>
                                    $session->id,
                            ])
                            ->saveQuietly();
                    }
                }

                /*
                 * Le statut est recalculé après toutes les allocations.
                 */
                foreach (
                    array_keys(
                        $allocations
                    )
                    as $besoinId
                ) {
                    app(
                        BesoinSessionAllocationService::class
                    )
                        ->synchroniserStatut(
                            BesoinFormation::findOrFail(
                                $besoinId
                            )
                        );
                }

                $effectifAffecte =
                    array_sum(
                        $allocations
                    );

                return [
                    'effectif_affecte' =>
                        $effectifAffecte,

                    'message' =>
                        sprintf(
                            '%s — %s du %s au %s : %d personne(s) prévues issues de %d besoin(s).',
                            $session
                                ->code_session,
                            $stage
                                ->libelle_court,
                            $debut
                                ->format(
                                    'd/m/Y'
                                ),
                            $fin
                                ->format(
                                    'd/m/Y'
                                ),
                            $effectifAffecte,
                            count(
                                $allocations
                            )
                        ),
                ];
            }
        );
    }

    private function buildPeriodFromDuration(
        Carbon $startDate,
        float $duree
    ): array {
        $start =
            $startDate
                ->copy()
                ->setTime(
                    8,
                    0
                );

        if ($duree <= 0.5) {
            return [
                $start,

                $startDate
                    ->copy()
                    ->setTime(
                        12,
                        0
                    ),
            ];
        }

        $joursComplets =
            (int) floor(
                $duree
            );

        $reste =
            $duree
            - $joursComplets;

        $demiJournee =
            $reste >= 0.5;

        $endDate =
            $startDate
                ->copy();

        for (
            $i = 1;
            $i < $joursComplets;
            $i++
        ) {
            $endDate =
                $this->nextWorkingDay(
                    $endDate
                );
        }

        if ($demiJournee) {
            $endDate =
                $this->nextWorkingDay(
                    $endDate
                );

            $end =
                $endDate
                    ->copy()
                    ->setTime(
                        12,
                        0
                    );
        } else {
            $end =
                $endDate
                    ->copy()
                    ->setTime(
                        16,
                        0
                    );
        }

        return [
            $start,
            $end,
        ];
    }

    private function nextWorkingDay(
        Carbon $date
    ): Carbon {
        $date =
            $date
                ->copy()
                ->addDay();

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }

    private function roomPreferenceScore(
        Salle $salle,
        Stage $stage,
        array $sallesInstructeurs
    ): int {
        if (
            $stage
                ->salle_preferentielle_id
            !== null
            && (int) $salle->id
                === (int) $stage
                    ->salle_preferentielle_id
        ) {
            return 0;
        }

        if (
            in_array(
                (int) $salle->id,
                $sallesInstructeurs,
                true
            )
        ) {
            return 1;
        }

        return 2;
    }

    private function priorityWeight(
        ?string $priorite
    ): int {
        /*
         * PRIORITE_NEUTRALISEE_V1
         *
         * La colonne historique reste en base pour compatibilité,
         * mais elle n'influence plus le moteur de planification.
         */
        return 0;
    }

    private function echecGroupe(
        Collection $besoins,
        string $message
    ): array {
        return [
            'sessions_created' =>
                0,

            'messages' => [
                $message,
            ],

            'failed' =>
                $besoins
                    ->count(),

            'failed_codes' =>
                $besoins
                    ->pluck(
                        'code_besoin'
                    )
                    ->filter()
                    ->values()
                    ->all(),
        ];
    }

    private function resultatVide(
        string $message
    ): array {
        return [
            'success' =>
                false,

            'message' =>
                $message,

            'suggestions' =>
                '',

            'planned' =>
                0,

            'conflicts' =>
                0,

            'skipped' =>
                0,

            'failed' =>
                0,

            'failed_codes' =>
                [],

            'sessions_created' =>
                0,

            'partial' =>
                0,

            'still_to_plan' =>
                0,

            'conflict_messages' => [
                $message,
            ],
        ];
    }
}
