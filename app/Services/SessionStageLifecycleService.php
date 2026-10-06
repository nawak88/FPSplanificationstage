<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\SessionStageHistorique;
use Throwable;

class SessionStageLifecycleService
{
    public function annuler(
        SessionStage $record,
        string $motif,
        ?string $commentaire = null
    ): array {
        return DB::transaction(
            function () use (
                $record,
                $motif,
                $commentaire
            ): array {
                $session =
                    SessionStage::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $record->getKey()
                        );

                if (
                    $session->statut
                    === 'annulee'
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            'Cette session est déjà annulée.',
                    ];
                }

                if (
                    $session->statut
                    === 'terminee'
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            'Une session terminée ne peut plus être annulée.',
                    ];
                }

                $ancienStatut =
                    $session->statut;

                /*
                 * ANNULATION_DEFINITIVE
                 *
                 * Une annulation ne remet jamais le besoin
                 * en "À planifier".
                 *
                 * Les inscriptions encore actives deviennent annulées.
                 * Les inscriptions déjà refusées ou annulées sont conservées.
                 */
                $inscriptionsAnnulees =
                    $session
                        ->inscriptions()
                        ->whereNotIn(
                            'statut',
                            [
                                'annulee',
                                'refusee',
                            ]
                        )
                        ->update([
                            'statut' =>
                                'annulee',
                        ]);

                $session->update([
                    'statut' =>
                        'annulee',
                ]);

                SessionStageHistorique::create([
                    'session_stage_id' =>
                        $session->id,

                    'type' =>
                        'annulation',

                    'ancien_debut' =>
                        $session->debut,

                    'ancien_fin' =>
                        $session->fin,

                    'ancien_statut' =>
                        $ancienStatut,

                    'nouveau_statut' =>
                        'annulee',

                    'motif' =>
                        $motif,

                    'commentaire' =>
                        $commentaire,

                    'meta' => [
                        'inscriptions_annulees' =>
                            $inscriptionsAnnulees,

                        'besoin_replanifie' =>
                            false,
                    ],
                ]);

                return [
                    'success' => true,

                    'message' =>
                        sprintf(
                            '%s est annulée définitivement.',
                            $session->code_session
                        ),

                    'inscriptions_annulees' =>
                        $inscriptionsAnnulees,
                ];
            }
        );
    }

    public function deplacer(
        SessionStage $record,
        string $nouvelleDate,
        string $motif,
        ?string $commentaire = null
    ): array {
        $date =
            Carbon::createFromFormat(
                'Y-m-d',
                $nouvelleDate
            )->startOfDay();

        if (
            $date->isWeekend()
        ) {
            return [
                'success' => false,
                'conflicts' => [
                    'La nouvelle date de début doit être comprise entre le lundi et le vendredi.',
                ],
            ];
        }

        DB::beginTransaction();

        try {
            $session =
                SessionStage::query()
                    ->with(
                        'instructeurs'
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $record->getKey()
                    );

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
                DB::rollBack();

                return [
                    'success' => false,
                    'conflicts' => [
                        'Une session annulée ou terminée ne peut plus être déplacée.',
                    ],
                ];
            }

            $ancienDebut =
                $session
                    ->debut
                    ->copy();

            $ancienneFin =
                $session
                    ->fin
                    ->copy();

            [
                $nouveauDebut,
                $nouvelleFin,
            ] = $this->decalerPeriode(
                $ancienDebut,
                $ancienneFin,
                $date
            );

            /*
             * Pour réutiliser exactement le détecteur de conflits
             * existant, on place temporairement LA session courante
             * hors de la période contrôlée à l'intérieur de la
             * transaction. Elle ne peut donc pas entrer en conflit
             * avec elle-même.
             *
             * Rien n'est visible en dehors de la transaction.
             */
            $session
                ->forceFill([
                    'debut' =>
                        Carbon::create(
                            1900,
                            1,
                            2,
                            8,
                            0,
                            0
                        ),

                    'fin' =>
                        Carbon::create(
                            1900,
                            1,
                            2,
                            16,
                            0,
                            0
                        ),
                ])
                ->saveQuietly();

            $data = [
                'stage_id' =>
                    $session->stage_id,

                'debut' =>
                    $nouveauDebut
                        ->format(
                            'Y-m-d H:i:s'
                        ),

                'fin' =>
                    $nouvelleFin
                        ->format(
                            'Y-m-d H:i:s'
                        ),

                'salle_id' =>
                    $session->salle_id,

                'capacite_min' =>
                    $session->capacite_min,

                'capacite_max' =>
                    $session->capacite_max,

                'instructeurs' =>
                    $session
                        ->instructeurs
                        ->pluck('id')
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->values()
                        ->all(),
            ];

            $conflicts =
                app(
                    SessionStageConflictDetector::class
                )
                    ->detect(
                        $data
                    );

            if (
                $conflicts !== []
            ) {
                DB::rollBack();

                return [
                    'success' => false,
                    'conflicts' =>
                        array_values(
                            array_unique(
                                $conflicts
                            )
                        ),
                ];
            }

            $session
                ->forceFill([
                    'debut' =>
                        $nouveauDebut,

                    'fin' =>
                        $nouvelleFin,
                ])
                ->save();

            SessionStageHistorique::create([
                'session_stage_id' =>
                    $session->id,

                'type' =>
                    'deplacement',

                'ancien_debut' =>
                    $ancienDebut,

                'ancien_fin' =>
                    $ancienneFin,

                'nouveau_debut' =>
                    $nouveauDebut,

                'nouveau_fin' =>
                    $nouvelleFin,

                'ancien_statut' =>
                    $session->statut,

                'nouveau_statut' =>
                    $session->statut,

                'motif' =>
                    $motif,

                'commentaire' =>
                    $commentaire,

                'meta' => [
                    'salle_id' =>
                        $session->salle_id,

                    'instructeurs_conserves' =>
                        $data[
                            'instructeurs'
                        ],

                    'inscriptions_conservees' =>
                        $session
                            ->inscriptions()
                            ->count(),
                ],
            ]);

            DB::commit();

            return [
                'success' => true,

                'message' =>
                    sprintf(
                        '%s déplacée du %s au %s. Les inscriptions, la salle et les formateurs sont conservés.',
                        $session->code_session,
                        $ancienDebut->format(
                            'd/m/Y'
                        ),
                        $nouveauDebut->format(
                            'd/m/Y'
                        )
                    ),

                'ancien_debut' =>
                    $ancienDebut,

                'ancien_fin' =>
                    $ancienneFin,

                'nouveau_debut' =>
                    $nouveauDebut,

                'nouveau_fin' =>
                    $nouvelleFin,
            ];
        } catch (Throwable $exception) {
            if (
                DB::transactionLevel()
                > 0
            ) {
                DB::rollBack();
            }

            throw $exception;
        }
    }

    private function decalerPeriode(
        Carbon $ancienDebut,
        Carbon $ancienneFin,
        Carbon $nouvelleDate
    ): array {
        $nouveauDebut =
            $nouvelleDate
                ->copy()
                ->setTime(
                    $ancienDebut->hour,
                    $ancienDebut->minute,
                    $ancienDebut->second
                );

        $joursOuvresASauter = 0;

        $cursor =
            $ancienDebut
                ->copy()
                ->startOfDay();

        $ancienneFinJour =
            $ancienneFin
                ->copy()
                ->startOfDay();

        while (
            $cursor->lt(
                $ancienneFinJour
            )
        ) {
            $cursor->addDay();

            if (
                ! $cursor
                    ->isWeekend()
            ) {
                $joursOuvresASauter++;
            }
        }

        $nouvelleFinJour =
            $nouvelleDate
                ->copy()
                ->startOfDay();

        for (
            $i = 0;
            $i < $joursOuvresASauter;
            $i++
        ) {
            do {
                $nouvelleFinJour
                    ->addDay();
            } while (
                $nouvelleFinJour
                    ->isWeekend()
            );
        }

        $nouvelleFin =
            $nouvelleFinJour
                ->copy()
                ->setTime(
                    $ancienneFin->hour,
                    $ancienneFin->minute,
                    $ancienneFin->second
                );

        if (
            $nouvelleFin
                ->lte(
                    $nouveauDebut
                )
        ) {
            throw new \RuntimeException(
                'La période actuelle de la session est invalide : impossible de calculer son déplacement.'
            );
        }

        return [
            $nouveauDebut,
            $nouvelleFin,
        ];
    }
}
