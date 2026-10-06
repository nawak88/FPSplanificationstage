<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;

class BesoinFormationPlanner
{
    public function plan(
        BesoinFormation $besoin
    ): array {
        return DB::transaction(
            function () use ($besoin): array {
                /*
                 * Verrouillage du besoin :
                 * empêche deux planifications
                 * simultanées du même besoin.
                 */
                $besoin = BesoinFormation::query()
                    ->whereKey(
                        $besoin->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $besoin) {
                    return [
                        'success' => false,
                        'conflicts' => [
                            'Le besoin de formation est introuvable.',
                        ],
                        'suggestions' => '',
                    ];
                }

                /*
                 * Un besoin déjà rattaché
                 * à une session ne doit jamais
                 * en créer une deuxième.
                 */
                if (
                    $besoin->session_stage_id
                    !== null
                ) {
                    $session =
                        SessionStage::find(
                            $besoin
                                ->session_stage_id
                        );

                    return [
                        'success' => false,
                        'already_planned' => true,
                        'session' => $session,
                        'conflicts' => [
                            $session
                                ? sprintf(
                                    'Ce besoin est déjà planifié dans la session %s.',
                                    $session
                                        ->code_session
                                )
                                : 'Ce besoin est déjà associé à une session.',
                        ],
                        'suggestions' => '',
                    ];
                }

                if (
                    $besoin->statut
                    === 'annule'
                ) {
                    return [
                        'success' => false,
                        'conflicts' => [
                            'Ce besoin est annulé et ne peut pas être planifié.',
                        ],
                        'suggestions' => '',
                    ];
                }

                $besoin->load(
                    'stage'
                );

                $stage =
                    $besoin->stage;

                if (! $stage instanceof Stage) {
                    return $this->failure(
                        $besoin,
                        [
                            'Le stage associé au besoin est introuvable.',
                        ]
                    );
                }

                $duree =
                    (float) $stage
                        ->duree_jours;

                if ($duree <= 0) {
                    return $this->failure(
                        $besoin,
                        [
                            'La durée du stage n’est pas renseignée dans le catalogue.',
                        ]
                    );
                }


                /*
                 * Formateurs actifs
                 * associés au stage.
                 */
                $instructeurIds =
                    $stage
                        ->instructeurs()
                        ->wherePivot(
                            'actif',
                            true
                        )
                        ->pluck(
                            'rh_marins.id'
                        )
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->all();

                if (
                    $instructeurIds
                    === []
                ) {
                    return $this->failure(
                        $besoin,
                        [
                            'Aucun formateur actif n’est associé à ce stage.',
                        ]
                    );
                }

                /*
                 * Deux fonctionnements distincts :
                 *
                 * dates_fixes :
                 * les dates demandées doivent
                 * correspondre à la durée réelle
                 * du stage.
                 *
                 * plage :
                 * les dates indiquent uniquement
                 * la fenêtre dans laquelle chercher
                 * UN créneau.
                 */
                if (
                    $besoin->type_periode
                    === 'dates_fixes'
                ) {
                    return $this
                        ->planFixedDates(
                            $besoin,
                            $stage,
                            $instructeurIds,
                            $duree
                        );
                }

                if (
                    $besoin->type_periode
                    === 'plage' || $besoin->type_periode === BesoinPeriodeService::TYPE_INDISPONIBILITE
                ) {
                    return $this
                        ->planDateRange(
                            $besoin,
                            $stage,
                            $instructeurIds,
                            $duree
                        );
                }

                return $this->failure(
                    $besoin,
                    [
                        'Le type de période du besoin est inconnu.',
                    ]
                );
            }
        );
    }

    private function planFixedDates(
        BesoinFormation $besoin,
        Stage $stage,
        array $instructeurIds,
        float $duree
    ): array {
        /*
         * MODE "DATE DE DÉBUT IMPOSÉE"
         *
         * Une seule date est nécessaire.
         * La date de fin de session est calculée automatiquement
         * à partir de la durée du stage.
         *
         * Exemple :
         * - début imposé : 17/09/2026
         * - durée : 15 jours
         * => le moteur calcule lui-même la date réelle de fin.
         */
        if (
            ! $besoin
                ->date_debut_souhaitee
        ) {
            return $this->failure(
                $besoin,
                [
                    'La date de début imposée n’est pas renseignée.',
                ]
            );
        }

        $dateDebut =
            Carbon::parse(
                $besoin
                    ->date_debut_souhaitee
            )->startOfDay();

        /*
         * DATE_FIXE_INTERDITE_LE_WEEKEND
         *
         * En mode "Date de début imposée", on ne décale pas
         * silencieusement une date choisie par l'utilisateur.
         * Si elle tombe un samedi ou un dimanche, on demande
         * explicitement un jour ouvré.
         *
         * Le calcul des jours suivants ignore déjà les week-ends
         * via nextWorkingDay().
         */
        if (
            $dateDebut->isWeekend()
        ) {
            return $this->failure(
                $besoin,
                [
                    sprintf(
                        'La date de début imposée (%s) tombe un samedi ou un dimanche. Choisissez un jour ouvré du lundi au vendredi.',
                        $dateDebut->format(
                            'd/m/Y'
                        )
                    ),
                ]
            );
        }

        [
            $debut,
            $fin,
        ] = $this
            ->buildPeriodFromDuration(
                $dateDebut,
                $duree
            );

        /*
         * date_fin_souhaitee n'est volontairement PAS utilisée
         * dans ce mode. Elle reste réservée au mode "Plage de dates".
         */
        return $this->tryPeriod(
            $besoin,
            $stage,
            $instructeurIds,
            $debut,
            $fin
        );
    }

    private function planDateRange(
        BesoinFormation $besoin,
        Stage $stage,
        array $instructeurIds,
        float $duree
    ): array {
        if (
            ! $besoin
                ->date_debut_souhaitee
            || ! $besoin
                ->date_fin_souhaitee
        ) {
            return $this->failure(
                $besoin,
                [
                    'La plage de dates n’est pas complète.',
                ]
            );
        }

        $rangeStart =
            Carbon::parse(
                $besoin
                    ->date_debut_souhaitee
            )->startOfDay();

        $rangeEnd =
            Carbon::parse(
                $besoin
                    ->date_fin_souhaitee
            )->endOfDay();

        if ($besoin->type_periode === BesoinPeriodeService::TYPE_INDISPONIBILITE) {
            [$rangeStart, $rangeEnd] = BesoinPeriodeService::unavailableSearchBounds($besoin);
        }

        if (
            $rangeEnd->lt(
                $rangeStart
            )
        ) {
            return $this->failure(
                $besoin,
                [
                    'La date de fin de la plage est antérieure à la date de début.',
                ]
            );
        }

        $candidateDate =
            $rangeStart->copy();

        $allConflicts = [];

        /*
         * La plage est uniquement
         * une fenêtre de recherche.
         *
         * Dès qu'un créneau fonctionne,
         * on crée UNE session et on quitte.
         */
        while (
            $candidateDate->lte(
                $rangeEnd
            )
        ) {
            /*
             * V1 :
             * pas de départ automatique
             * samedi ou dimanche.
             */
            if (
                $candidateDate
                    ->isWeekend()
            ) {
                $candidateDate
                    ->addDay();

                continue;
            }

            [
                $debut,
                $fin,
            ] = $this
                ->buildPeriodFromDuration(
                    $candidateDate,
                    $duree
                );

            /*
             * La totalité du stage doit
             * tenir dans la plage autorisée.
             */
            if (
                $fin->gt(
                    $rangeEnd
                )
            ) {
                break;
            }

            if ($besoin->type_periode === BesoinPeriodeService::TYPE_INDISPONIBILITE
                && !BesoinPeriodeService::avoidsUnavailablePeriod($besoin, $debut, $fin)) {
                $candidateDate->addDay();
                continue;
            }

            $result =
                $this->tryPeriod(
                    $besoin,
                    $stage,
                    $instructeurIds,
                    $debut,
                    $fin,
                    false
                );

            if (
                $result['success']
                ?? false
            ) {
                /*
                 * IMPORTANT :
                 * arrêt immédiat au premier
                 * créneau compatible.
                 */
                return $result;
            }

            $allConflicts =
                array_merge(
                    $allConflicts,
                    $result[
                        'conflicts'
                    ] ?? []
                );

            $candidateDate
                ->addDay();
        }

        $allConflicts =
            array_values(
                array_unique(
                    $allConflicts
                )
            );

        if (
            $allConflicts
            === []
        ) {
            $allConflicts[] =
                'Aucun créneau compatible n’a été trouvé dans la plage demandée.';
        }

        return $this->failure(
            $besoin,
            $allConflicts
        );
    }

    private function tryPeriod(
        BesoinFormation $besoin,
        Stage $stage,
        array $instructeurIds,
        Carbon $debut,
        Carbon $fin,
        bool $markConflict = true
    ): array {
        /*
         * Sécurité supplémentaire :
         * pas de deuxième session.
         */
        $besoin->refresh();

        if (
            $besoin
                ->session_stage_id
            !== null
        ) {
            return [
                'success' => false,
                'already_planned' => true,
                'conflicts' => [
                    'Une session existe déjà pour ce besoin.',
                ],
                'suggestions' => '',
            ];
        }

        /*
         * BESOIN_ESTIMATIF_NE_RESERVE_PAS_DE_PLACE
         *
         * nombre_stagiaires décrit le besoin prévisionnel exprimé
         * par l'unité. Il ne réserve aucune place et ne réduit jamais
         * la capacité d'une session.
         *
         * La salle et la session sont dimensionnées selon le catalogue
         * du stage. Les places réellement occupées proviennent ensuite
         * des inscriptions/candidatures.
         */
        $capaciteNecessaire = $stage->capacite_max;

        /*
         * Les préférences de salle ne font pas partie du référentiel
         * RH. La salle préférentielle du stage reste prioritaire.
         */
        $sallesInstructeurs = [];

        $salles =
            Salle::query()
                ->where(
                    'actif',
                    true
                )
                ->when(
                    $capaciteNecessaire
                    !== null,
                    function (
                        $query
                    ) use (
                        $capaciteNecessaire
                    ): void {
                        $query->where(
                            function (
                                $query
                            ) use (
                                $capaciteNecessaire
                            ): void {
                                $query
                                    ->whereNull(
                                        'capacite'
                                    )
                                    ->orWhere(
                                        'capacite',
                                        '>=',
                                        $capaciteNecessaire
                                    );
                            }
                        );
                    }
                )
                ->get()
                ->sort(
                    function (
                        Salle $a,
                        Salle $b
                    ) use (
                        $stage,
                        $sallesInstructeurs
                    ): int {
                        $scoreA =
                            $this->roomPreferenceScore(
                                $a,
                                $stage,
                                $sallesInstructeurs
                            );

                        $scoreB =
                            $this->roomPreferenceScore(
                                $b,
                                $stage,
                                $sallesInstructeurs
                            );

                        if (
                            $scoreA
                            !== $scoreB
                        ) {
                            return $scoreA
                                <=>
                                $scoreB;
                        }

                        $capacityA =
                            $a->capacite
                            ?? PHP_INT_MAX;

                        $capacityB =
                            $b->capacite
                            ?? PHP_INT_MAX;

                        if (
                            $capacityA
                            !== $capacityB
                        ) {
                            return $capacityA
                                <=>
                                $capacityB;
                        }

                        return strcmp(
                            $a->nom,
                            $b->nom
                        );
                    }
                )
                ->values();

        if (
            $salles->isEmpty()
        ) {
            $conflicts = [
                'Aucune salle active ne possède une capacité suffisante.',
            ];

            if (! $markConflict) {
                return [
                    'success' => false,
                    'conflicts' =>
                        $conflicts,
                ];
            }

            return $this->failure(
                $besoin,
                $conflicts
            );
        }

        $detector = app(
            SessionStageConflictDetector::class
        );

        $allConflicts = [];

        foreach (
            $salles
            as $salle
        ) {
            $data = [
                'stage_id' =>
                    $stage->id,

                'debut' =>
                    $debut->format(
                        'Y-m-d H:i:s'
                    ),

                'fin' =>
                    $fin->format(
                        'Y-m-d H:i:s'
                    ),

                'salle_id' =>
                    $salle->id,

                'capacite_min' =>
                    $stage
                        ->capacite_min,

                'capacite_max' =>
                    $capaciteNecessaire,

                'instructeurs' =>
                    $instructeurIds,
            ];

            $conflicts =
                $detector->detect(
                    $data
                );

            if (
                $conflicts
                !== []
            ) {
                $allConflicts =
                    array_merge(
                        $allConflicts,
                        $conflicts
                    );

                continue;
            }

            /*
             * Première combinaison
             * date + salle compatible :
             * création d'UNE session.
             */
            return $this->createSession(
                $besoin,
                $stage,
                $instructeurIds,
                $salle,
                $debut,
                $fin
            );
        }

        $allConflicts =
            array_values(
                array_unique(
                    $allConflicts
                )
            );

        if (! $markConflict) {
            return [
                'success' => false,
                'conflicts' =>
                    $allConflicts,
            ];
        }

        /*
         * Pour des dates fixes,
         * on peut proposer des solutions.
         */
        $baseSalleId =
            $stage
                ->salle_preferentielle_id
            ?? $salles
                ->first()
                ?->id;

        $alternativeData = [
            'stage_id' =>
                $stage->id,

            'debut' =>
                $debut->format(
                    'Y-m-d H:i:s'
                ),

            'fin' =>
                $fin->format(
                    'Y-m-d H:i:s'
                ),

            'salle_id' =>
                $baseSalleId,

            'capacite_min' =>
                $stage
                    ->capacite_min,

            'capacite_max' =>
                $capaciteNecessaire,

            'instructeurs' =>
                $instructeurIds,
        ];

        $alternativeFinder = app(
            SessionStageAlternativeFinder::class
        );

        $suggestions =
            $alternativeFinder->find(
                $alternativeData
            );

        $suggestionText =
            $alternativeFinder
                ->formatForNotification(
                    $suggestions
                );

        return $this->failure(
            $besoin,
            $allConflicts,
            $suggestionText
        );
    }

    private function createSession(
        BesoinFormation $besoin,
        Stage $stage,
        array $instructeurIds,
        Salle $salle,
        Carbon $debut,
        Carbon $fin
    ): array {
        /*
         * Dernière vérification juste
         * avant la création.
         */
        $besoin->refresh();

        if (
            $besoin
                ->session_stage_id
            !== null
        ) {
            return [
                'success' => false,
                'already_planned' => true,
                'conflicts' => [
                    'Une session existe déjà pour ce besoin.',
                ],
                'suggestions' => '',
            ];
        }

        $capaciteMax = $stage->capacite_max;

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
                    $capaciteMax,

                'statut' =>
                    'planifiee',

                'salle_forcee' =>
                    false,

                'source' =>
                    'besoin',

                'commentaire' =>
                    'Créée automatiquement depuis '
                    . $besoin
                        ->code_besoin
                    . ' — '
                    . $besoin
                        ->demandeur,
            ]);

        $session
            ->instructeurs()
            ->sync(
                $instructeurIds
            );

        $besoin->update([
            'session_stage_id' =>
                $session->id,

            'statut' =>
                'planifie',
        ]);

        return [
            'success' => true,

            'session' =>
                $session,

            'message' =>
                sprintf(
                    '%s a été créée du %s au %s dans la salle %s.',
                    $session
                        ->code_session,
                    $debut->format(
                        'd/m/Y H:i'
                    ),
                    $fin->format(
                        'd/m/Y H:i'
                    ),
                    $salle->nom
                ),
        ];
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

        /*
         * Demi-journée :
         * par défaut 08:00 -> 12:00.
         */
        if (
            $duree <= 0.5
        ) {
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
            $startDate->copy();

        /*
         * Journées complètes.
         */
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

        /*
         * Exemple :
         * 1,5 jour =
         * lundi 08:00
         * mardi 12:00.
         */
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

        while (
            $date->isWeekend()
        ) {
            $date->addDay();
        }

        return $date;
    }

    private function roomPreferenceScore(
        Salle $salle,
        Stage $stage,
        array $sallesInstructeurs
    ): int {
        /*
         * Priorité 1 :
         * salle préférentielle du stage.
         */
        if (
            $stage
                ->salle_preferentielle_id
            === $salle->id
        ) {
            return 0;
        }

        /*
         * Priorité 2 :
         * salle préférentielle
         * d'un formateur.
         */
        if (
            in_array(
                $salle->id,
                $sallesInstructeurs,
                true
            )
        ) {
            return 1;
        }

        /*
         * Priorité 3 :
         * autre salle compatible.
         */
        return 2;
    }

    private function formatDuration(
        float $duree
    ): string {
        if (
            floor($duree)
            === $duree
        ) {
            return (string)
                (int) $duree;
        }

        return str_replace(
            '.',
            ',',
            rtrim(
                rtrim(
                    number_format(
                        $duree,
                        2,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            )
        );
    }

    private function failure(
        BesoinFormation $besoin,
        array $conflicts,
        string $suggestions = ''
    ): array {
        /*
         * Un besoin déjà planifié
         * ne doit jamais repasser
         * en conflit.
         */
        if (
            $besoin
                ->session_stage_id
            === null
        ) {
            $besoin->update([
                'statut' =>
                    'conflit',
            ]);
        }

        return [
            'success' => false,

            'conflicts' =>
                array_values(
                    array_unique(
                        $conflicts
                    )
                ),

            'suggestions' =>
                $suggestions,
        ];
    }
}
