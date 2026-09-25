<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;

class Planning extends Page
{
    use RequiresAuthentication;

    protected string $view =
        'fpsplanificationstage::filament.pages.planning';

    protected static ?string $navigationLabel =
        'Planning / Calendrier';

    protected static string|\UnitEnum|null $navigationGroup =
        'Planification';

    protected static ?int $navigationSort = 30;

    public string $mode = 'month';

    public string $currentDate;

    public string $stageFilter = '';

    public string $instructeurFilter = '';

    public string $salleFilter = '';

    public string $statutFilter = '';

    public function mount(): void
    {
        $this->currentDate =
            Carbon::today()->format('Y-m-d');
    }

    public function getTitle(): string
    {
        return 'Planning / Calendrier';
    }

    public function setMode(string $mode): void
    {
        if (! in_array(
            $mode,
            ['month', 'week'],
            true
        )) {
            return;
        }

        $this->mode = $mode;
    }

    public function previousPeriod(): void
    {
        $date = Carbon::parse(
            $this->currentDate
        );

        if ($this->mode === 'week') {
            $date->subWeek();
        } else {
            $date->subMonthNoOverflow();
        }

        $this->currentDate =
            $date->format('Y-m-d');
    }

    public function nextPeriod(): void
    {
        $date = Carbon::parse(
            $this->currentDate
        );

        if ($this->mode === 'week') {
            $date->addWeek();
        } else {
            $date->addMonthNoOverflow();
        }

        $this->currentDate =
            $date->format('Y-m-d');
    }

    public function goToToday(): void
    {
        $this->currentDate =
            Carbon::today()->format('Y-m-d');
    }

    public function resetFilters(): void
    {
        $this->stageFilter = '';
        $this->instructeurFilter = '';
        $this->salleFilter = '';
        $this->statutFilter = '';
    }

    public function filterKey(): string
    {
        return md5(implode(
            '|',
            [
                $this->stageFilter,
                $this->instructeurFilter,
                $this->salleFilter,
                $this->statutFilter,
            ]
        ));
    }

    public function filterOptions(): array
    {
        return [
            'stages' => Stage::query()
                ->where('actif', true)
                ->orderBy('libelle_court')
                ->get()
                ->map(
                    fn (Stage $stage): array => [
                        'id' => $stage->id,
                        'label' =>
                            ($stage->code_stage
                                ? $stage->code_stage . ' — '
                                : '')
                            . $stage->libelle_court,
                    ]
                )
                ->all(),

            'instructeurs' => Marin::query()
                ->whereIn(
                    'rh_marins.id',
                    SessionStage::query()
                        ->join(
                            'instructeur_session_stage',
                            'session_stages.id',
                            '=',
                            'instructeur_session_stage.session_stage_id'
                        )
                        ->select(
                            'instructeur_session_stage.instructeur_id'
                        )
                )
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get()
                ->map(
                    fn (Marin $instructeur): array => [
                        'id' => $instructeur->id,
                        'label' => trim(
                            mb_strtoupper(
                                $instructeur->nom
                            )
                            . ' '
                            . $instructeur->prenom
                        ),
                    ]
                )
                ->all(),

            'salles' => Salle::query()
                ->where('actif', true)
                ->orderBy('nom')
                ->get()
                ->map(
                    fn (Salle $salle): array => [
                        'id' => $salle->id,
                        'label' =>
                            ($salle->code
                                ? $salle->code . ' — '
                                : '')
                            . $salle->nom,
                    ]
                )
                ->all(),
        ];
    }

    public function calendarData(): array
    {
        $reference = Carbon::parse(
            $this->currentDate
        );

        $monthStart = $reference
            ->copy()
            ->startOfMonth()
            ->startOfDay();

        $monthEnd = $reference
            ->copy()
            ->endOfMonth()
            ->endOfDay();

        $gridStart = $monthStart
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->startOfDay();

        $gridEnd = $monthEnd
            ->copy()
            ->endOfWeek(Carbon::SUNDAY)
            ->endOfDay();

        $sessions = $this->getSessions(
            $gridStart,
            $gridEnd
        );

        $days = [];

        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $day = $cursor->copy();

            $dayStart = $day
                ->copy()
                ->startOfDay();

            $dayEnd = $day
                ->copy()
                ->endOfDay();

            $events = [];

            foreach ($sessions as $session) {
                $sessionStart =
                    $session->debut->copy();

                $sessionEnd =
                    $session->fin->copy();

                if (
                    ! $sessionStart->lte($dayEnd)
                    || ! $sessionEnd->gte($dayStart)
                ) {
                    continue;
                }

                $events[] =
                    $this->buildEvent(
                        $session,
                        $this->formatMonthPeriod(
                            $sessionStart,
                            $sessionEnd,
                            $day
                        )
                    );
            }

            $date =
                $day->format('Y-m-d');

            $days[] = [
                'date' =>
                    $date,

                'day' =>
                    $day->day,

                'is_current_month' =>
                    $day->month ===
                    $reference->month,

                'is_today' =>
                    $day->isToday(),

                'create_url' =>
                    SessionStageResource::getUrl(
                        'create',
                        [
                            'date' => $date,
                        ]
                    ),

                'events' =>
                    $events,
            ];

            $cursor->addDay();
        }

        return [
            'label' => ucfirst(
                $monthStart
                    ->locale('fr')
                    ->translatedFormat('F Y')
            ),

            'days' => $days,
        ];
    }

    public function weekData(): array
    {
        $reference = Carbon::parse(
            $this->currentDate
        );

        $weekStart = $reference
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->startOfDay();

        $weekEnd = $weekStart
            ->copy()
            ->addDays(4)
            ->endOfDay();

        $sessions = $this->getSessions(
            $weekStart,
            $weekEnd
        );

        $days = [];

        for ($i = 0; $i < 5; $i++) {
            $day = $weekStart
                ->copy()
                ->addDays($i);

            $businessStart = $day
                ->copy()
                ->setTime(8, 0);

            $businessEnd = $day
                ->copy()
                ->setTime(16, 0);

            $events = [];

            foreach ($sessions as $session) {
                $sessionStart =
                    $session->debut->copy();

                $sessionEnd =
                    $session->fin->copy();

                $visibleStart =
                    $sessionStart->greaterThan(
                        $businessStart
                    )
                        ? $sessionStart
                        : $businessStart->copy();

                $visibleEnd =
                    $sessionEnd->lessThan(
                        $businessEnd
                    )
                        ? $sessionEnd
                        : $businessEnd->copy();

                if (
                    $visibleEnd->lessThanOrEqualTo(
                        $visibleStart
                    )
                ) {
                    continue;
                }

                $startMinutes =
                    $businessStart->diffInMinutes(
                        $visibleStart
                    );

                $durationMinutes =
                    $visibleStart->diffInMinutes(
                        $visibleEnd
                    );

                $event =
                    $this->buildEvent(
                        $session,
                        $this->formatWeekPeriod(
                            $sessionStart,
                            $sessionEnd,
                            $day
                        )
                    );

                $event['start_minutes'] =
                    $startMinutes;

                $event['end_minutes'] =
                    $startMinutes
                    + $durationMinutes;

                $events[] = $event;
            }

            usort(
                $events,
                fn (
                    array $a,
                    array $b
                ): int =>
                    $a['start_minutes']
                    <=>
                    $b['start_minutes']
            );

            $laneEnds = [];
            $maxLanes = 1;

            foreach ($events as $index => $event) {
                $lane = 0;

                while (
                    isset($laneEnds[$lane])
                    && $laneEnds[$lane]
                        > $event['start_minutes']
                ) {
                    $lane++;
                }

                $laneEnds[$lane] =
                    $event['end_minutes'];

                $events[$index]['lane'] =
                    $lane;

                $maxLanes = max(
                    $maxLanes,
                    $lane + 1
                );
            }

            foreach ($events as $index => $event) {
                $events[$index]['top'] =
                    ($event['start_minutes'] / 480)
                    * 100;

                $events[$index]['height'] =
                    (
                        (
                            $event['end_minutes']
                            - $event['start_minutes']
                        )
                        / 480
                    ) * 100;

                $events[$index]['left'] =
                    ($event['lane'] / $maxLanes)
                    * 100;

                $events[$index]['width'] =
                    100 / $maxLanes;
            }

            $date =
                $day->format('Y-m-d');

            $createUrls = [];

            for (
                $hour = 8;
                $hour <= 15;
                $hour++
            ) {
                $heure =
                    sprintf(
                        '%02d:00',
                        $hour
                    );

                $createUrls[$heure] =
                    SessionStageResource::getUrl(
                        'create',
                        [
                            'date' =>
                                $date,

                            'heure' =>
                                $heure,
                        ]
                    );
            }

            $days[] = [
                'date' =>
                    $date,

                'label' =>
                    ucfirst(
                        $day
                            ->locale('fr')
                            ->translatedFormat(
                                'D d/m'
                            )
                    ),

                'is_today' =>
                    $day->isToday(),

                'create_url' =>
                    SessionStageResource::getUrl(
                        'create',
                        [
                            'date' => $date,
                        ]
                    ),

                'create_urls' =>
                    $createUrls,

                'events' =>
                    $events,
            ];
        }

        return [
            'label' =>
                sprintf(
                    'Semaine %d — du %s au %s',
                    $weekStart->isoWeek(),
                    $weekStart->format('d/m/Y'),
                    $weekEnd->format('d/m/Y')
                ),

            'days' => $days,
        ];
    }

    private function getSessions(
        Carbon $start,
        Carbon $end
    ) {
        $query = SessionStage::query()
            ->with([
                'stage',
                'salle',
                'instructeurs',
            ])
            ->where(
                'debut',
                '<=',
                $end
            )
            ->where(
                'fin',
                '>=',
                $start
            );

        if ($this->stageFilter !== '') {
            $query->where(
                'stage_id',
                (int) $this->stageFilter
            );
        }

        if ($this->salleFilter !== '') {
            $query->where(
                'salle_id',
                (int) $this->salleFilter
            );
        }

        /*
         * PLANNING_MASQUE_ANNULEES_PAR_DEFAUT
         *
         * Une session annulée reste accessible dans l'historique
         * et via le filtre "Annulée", mais elle ne pollue pas
         * le planning opérationnel par défaut.
         */
        if ($this->statutFilter !== '') {
            $query->where(
                'statut',
                $this->statutFilter
            );
        } else {
            $query->where(
                'statut',
                '<>',
                'annulee'
            );
        }

        if ($this->instructeurFilter !== '') {
            $instructeurId =
                (int) $this->instructeurFilter;

            $query->whereHas(
                'instructeurs',
                fn ($query) =>
                    $query->where(
                        'rh_marins.id',
                        $instructeurId
                    )
            );
        }

        return $query
            ->orderBy('debut')
            ->get();
    }

    private function buildEvent(
        SessionStage $session,
        string $periode
    ): array {
        $instructeurs = $session
            ->instructeurs
            ->map(
                fn ($instructeur): string =>
                    trim(
                        mb_strtoupper(
                            $instructeur->nom
                        )
                        . ' '
                        . $instructeur->prenom
                    )
            )
            ->implode(', ');

        return [
            'id' =>
                $session->id,

            'code' =>
                $session->code_session,

            'stage' =>
                $session
                    ->stage
                    ?->libelle_court
                ?? 'Stage non renseigné',

            'stage_code' =>
                $session
                    ->stage
                    ?->code_stage,

            'salle' =>
                $session
                    ->salle
                    ?->nom,

            'instructeurs' =>
                $instructeurs,

            'periode' =>
                $periode,

            'statut' =>
                $session->statut,

            'url' =>
                SessionStageResource::getUrl(
                    'edit',
                    [
                        'record' =>
                            $session->getKey(),
                    ]
                ),
        ];
    }

    private function formatMonthPeriod(
        Carbon $start,
        Carbon $end,
        Carbon $day
    ): string {
        if ($start->isSameDay($end)) {
            return sprintf(
                '%s → %s',
                $start->format('H:i'),
                $end->format('H:i')
            );
        }

        if ($start->isSameDay($day)) {
            return 'Début '
                . $start->format('H:i');
        }

        if ($end->isSameDay($day)) {
            return 'Fin '
                . $end->format('H:i');
        }

        return 'Journée complète';
    }

    private function formatWeekPeriod(
        Carbon $start,
        Carbon $end,
        Carbon $day
    ): string {
        if ($start->isSameDay($end)) {
            return sprintf(
                '%s → %s',
                $start->format('H:i'),
                $end->format('H:i')
            );
        }

        if ($start->isSameDay($day)) {
            return $start->format('H:i')
                . ' → 16:00';
        }

        if ($end->isSameDay($day)) {
            return '08:00 → '
                . $end->format('H:i');
        }

        return '08:00 → 16:00';
    }
}
