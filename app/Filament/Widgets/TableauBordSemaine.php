<?php

namespace Modules\FPSplanificationstage\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Widget;
use Modules\FPSplanificationstage\Filament\Pages\Statistiques;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\BesoinFormationResource;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\InscriptionResource;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;

class TableauBordSemaine extends Widget
{
    protected string $view =
        'fpsplanificationstage::filament.widgets.tableau-bord-semaine';

    protected int | string | array $columnSpan =
        'full';

    public string $currentDate;

    public function mount(): void
    {
        $this->currentDate =
            Carbon::today()->format('Y-m-d');
    }

    public function previousWeek(): void
    {
        $this->currentDate =
            Carbon::parse(
                $this->currentDate
            )
                ->subWeek()
                ->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->currentDate =
            Carbon::parse(
                $this->currentDate
            )
                ->addWeek()
                ->format('Y-m-d');
    }

    public function goToCurrentWeek(): void
    {
        $this->currentDate =
            Carbon::today()->format('Y-m-d');
    }

    public function dashboardData(): array
    {
        $reference =
            Carbon::parse(
                $this->currentDate
            );

        $weekStart =
            $reference
                ->copy()
                ->startOfWeek(
                    Carbon::MONDAY
                )
                ->startOfDay();

        $weekEnd =
            $weekStart
                ->copy()
                ->addDays(4)
                ->endOfDay();

        $sessions =
            SessionStage::query()->where('statut', '<>', 'annulee') /* DASHBOARD_MASQUE_SESSIONS_ANNULEES */
                ->with([
                    'stage',
                    'salle',
                ])
                ->withCount([
                    'inscriptions as participants_count' =>
                        fn ($query) =>
                            $query->whereIn(
                                'statut',
                                [
                                    'attente_nemo',
                                    'confirmee',
                                    'attente_derogation',
                                ]
                            ),
                ])
                ->where(
                    'debut',
                    '<=',
                    $weekEnd
                )
                ->where(
                    'fin',
                    '>=',
                    $weekStart
                )
                ->whereNotIn(
                    'statut',
                    [
                        'annulee',
                    ]
                )
                ->orderBy(
                    'debut'
                )
                ->get();

        $days = [];

        for (
            $i = 0;
            $i < 5;
            $i++
        ) {
            $day =
                $weekStart
                    ->copy()
                    ->addDays($i);

            $dayStart =
                $day
                    ->copy()
                    ->startOfDay();

            $dayEnd =
                $day
                    ->copy()
                    ->endOfDay();

            $events = [];

            foreach (
                $sessions
                as $session
            ) {
                if (
                    ! $session
                        ->debut
                        ->lte($dayEnd)
                    || ! $session
                        ->fin
                        ->gte($dayStart)
                ) {
                    continue;
                }

                $participants =
                    (int)
                    $session
                        ->participants_count;

                $capacite =
                    $session
                        ->capacite_max;

                $restantes =
                    $capacite !== null
                        ? max(
                            0,
                            $capacite
                            - $participants
                        )
                        : null;

                $events[] = [
                    'code' =>
                        $session
                            ->code_session,

                    'stage' =>
                        $session
                            ->stage
                            ?->libelle_court
                        ?? 'Stage',

                    'debut' =>
                        $session
                            ->debut
                            ->format('H:i'),

                    'fin' =>
                        $session
                            ->fin
                            ->format('H:i'),

                    'salle' =>
                        $session
                            ->salle
                            ?->nom,

                    'participants' =>
                        $participants,

                    'capacite' =>
                        $capacite,

                    'restantes' =>
                        $restantes,

                    'url' =>
                        SessionStageResource::getUrl(
                            'edit',
                            [
                                'record' =>
                                    $session
                                        ->getKey(),
                            ]
                        ),
                ];
            }

            $days[] = [
                'label' =>
                    ucfirst(
                        $day
                            ->locale('fr')
                            ->translatedFormat(
                                'l d F'
                            )
                    ),

                'is_today' =>
                    $day->isToday(),

                'events' =>
                    $events,
            ];
        }

        $participantsTotal =
            $sessions->sum(
                fn (
                    SessionStage $session
                ): int =>
                    (int)
                    $session
                        ->participants_count
            );

        $placesRestantes =
            $sessions
                ->filter(
                    fn (
                        SessionStage $session
                    ): bool =>
                        $session
                            ->capacite_max
                        !== null
                )
                ->sum(
                    fn (
                        SessionStage $session
                    ): int =>
                        max(
                            0,
                            $session
                                ->capacite_max
                            - (int)
                                $session
                                    ->participants_count
                        )
                );

        $besoinsAPlanifier =
            BesoinFormation::query()
                ->where(
                    'statut',
                    'a_planifier'
                )
                ->count();

        $candidaturesQuery =
            Inscription::query()
                ->whereIn(
                    'statut',
                    [
                        'attente_nemo',
                        'attente_derogation',
                    ]
                );

        $candidaturesATraiter =
            (clone $candidaturesQuery)
                ->count();

        $candidatures =
            (clone $candidaturesQuery)
                ->with([
                    'sessionStage.stage',
                ])
                ->orderByRaw(
                    "CASE
                        WHEN statut = 'attente_derogation'
                        THEN 0
                        ELSE 1
                    END"
                )
                ->orderByDesc(
                    'created_at'
                )
                ->limit(8)
                ->get()
                ->map(
                    function (
                        Inscription $inscription
                    ): array {
                        $statutLabel =
                            match (
                                $inscription->statut
                            ) {
                                'attente_derogation' =>
                                    'Dérogation à examiner',

                                'attente_nemo' =>
                                    'NEMO attendu',

                                default =>
                                    $inscription->statut,
                            };

                        $statutClass =
                            match (
                                $inscription->statut
                            ) {
                                'attente_derogation' =>
                                    'danger',

                                'attente_nemo' =>
                                    'warning',

                                default =>
                                    'neutral',
                            };

                        return [
                            'code' =>
                                $inscription
                                    ->code_inscription,

                            'nom' =>
                                trim(
                                    mb_strtoupper(
                                        $inscription->nom
                                    )
                                    . ' '
                                    . $inscription->prenom
                                ),

                            'email' =>
                                $inscription
                                    ->email,

                            'stage' =>
                                $inscription
                                    ->sessionStage
                                    ?->stage
                                    ?->libelle_court
                                ?? 'Stage non renseigné',

                            'session' =>
                                $inscription
                                    ->sessionStage
                                    ?->code_session
                                ?? 'Session non renseignée',

                            'statut_label' =>
                                $statutLabel,

                            'statut_class' =>
                                $statutClass,

                            'url' =>
                                InscriptionResource::getUrl(
                                    'edit',
                                    [
                                        'record' =>
                                            $inscription
                                                ->getKey(),
                                    ]
                                ),
                        ];
                    }
                )
                ->values()
                ->all();

        return [
            'week_label' =>
                sprintf(
                    'Semaine %d — du %s au %s',
                    $weekStart->isoWeek(),
                    $weekStart->format(
                        'd/m/Y'
                    ),
                    $weekEnd->format(
                        'd/m/Y'
                    )
                ),

            'sessions_count' =>
                $sessions->count(),

            'participants_count' =>
                $participantsTotal,

            'places_restantes' =>
                $placesRestantes,

            'besoins_a_planifier' =>
                $besoinsAPlanifier,

            'candidatures_a_traiter' =>
                $candidaturesATraiter,

            'candidatures' =>
                $candidatures,

            'days' =>
                $days,

            'urls' => [
                'planning' =>
                    SessionStageResource::getUrl(
                        'planning',
                        panel:
                            'fpsplanificationstage'
                    ),

                'besoins' =>
                    BesoinFormationResource::getUrl(
                        'index',
                        [
                            'tableFilters' => [
                                'statut' => [
                                    'value' =>
                                        'a_planifier',
                                ],
                            ],
                        ]
                    ),

                'inscriptions' =>
                    InscriptionResource::getUrl(
                        'index',
                        [
                            'tableFilters' => [
                                'statut' => [
                                    'values' => [
                                        'attente_nemo',
                                        'attente_derogation',
                                    ],
                                ],
                            ],
                        ]
                    ),

                'statistiques' =>
                    Statistiques::getUrl(),
            ],
        ];
    }
}
