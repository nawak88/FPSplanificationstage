<?php

namespace Modules\FPSplanificationstage\Filament\Widgets;

use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\FetchInfo;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Models\SessionStage;

class PlanningCalendar extends CalendarWidget
{
    protected bool $eventClickEnabled = true;

    public ?string $stageFilter = '';

    public ?string $instructeurFilter = '';

    public ?string $salleFilter = '';

    public ?string $statutFilter = '';

    public ?string $searchTerm = '';

    public ?string $viewMode = 'month';

    public function getCalendarView(): CalendarViewType
    {
        return match ($this->viewMode) {
            'week' => CalendarViewType::DayGridWeek,
            'list' => CalendarViewType::ListMonth,
            default => CalendarViewType::DayGridMonth,
        };
    }

    protected function getEvents(FetchInfo $info): Collection | array | Builder
    {
        $query = SessionStage::query()
            ->with([
                'stage',
                'salle',
                'instructeurs',
            ])
            ->where('debut', '<=', $info->end)
            ->where('fin', '>=', $info->start);

        if ($this->stageFilter !== '') {
            $query->where('stage_id', (int) $this->stageFilter);
        }

        if ($this->salleFilter !== '') {
            $query->where('salle_id', (int) $this->salleFilter);
        }

        if ($this->instructeurFilter !== '') {
            $query->whereHas(
                'instructeurs',
                fn ($query) => $query->where('rh_marins.id', (int) $this->instructeurFilter)
            );
        }

        if ($this->statutFilter !== '') {
            $query->where('statut', $this->statutFilter);
        }

        $searchTerm = trim((string) $this->searchTerm);

        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';

            $query->whereHas('stage', function ($stageQuery) use ($like) {
                $stageQuery->where(function ($where) use ($like) {
                    $where->where('libelle_court', 'like', $like)
                        ->orWhere('libelle_long', 'like', $like)
                        ->orWhere('lieux_formation', 'like', $like)
                        ->orWhere('centre_formation', 'like', $like)
                        ->orWhere('service_emetteur', 'like', $like);
                });
            });
        }

        return $query
            ->get()
            ->sort(
                fn (
                    SessionStage $a,
                    SessionStage $b
                ): int =>
                    $this->compareSessions(
                        $a,
                        $b
                    )
            )
            ->map(function (SessionStage $session): CalendarEvent {
                $title = trim(
                    ($session->stage?->code_stage ? $session->stage->code_stage . ' — ' : '')
                    . ($session->stage?->libelle_court ?? 'Session')
                );

                return CalendarEvent::make($session)
                    ->title($title)
                    ->start($session->debut)
                    ->end($session->fin)
                    ->backgroundColor($this->eventColor($session))
                    ->textColor('#ffffff')
;
            })
            ->values()
            ->all();
    }

    protected function compareSessions(
        SessionStage $a,
        SessionStage $b
    ): int {
        $startComparison =
            $a->debut->timestamp
            <=> $b->debut->timestamp;

        if ($startComparison !== 0) {
            return $startComparison;
        }

        /*
         * À heure de début identique, le stage le plus long
         * prend la première ligne du calendrier.
         * Cela évite l'effet "escalier".
         */
        $durationA =
            $a->fin->timestamp
            - $a->debut->timestamp;

        $durationB =
            $b->fin->timestamp
            - $b->debut->timestamp;

        $durationComparison =
            $durationB <=> $durationA;

        if ($durationComparison !== 0) {
            return $durationComparison;
        }

        return
            ((int) ($a->id ?? PHP_INT_MAX))
            <=>
            ((int) ($b->id ?? PHP_INT_MAX));
    }

    protected function eventColor(
        SessionStage $session
    ): string {
        if ($session->statut === 'annulee') {
            return '#dc2626';
        }

        if ($session->statut === 'terminee') {
            return '#64748b';
        }

        if ($session->statut === 'brouillon') {
            return '#94a3b8';
        }

        /*
         * Planifiée / confirmée : couleur déterminée par le stage.
         * Deux sessions du même stage ont la même couleur.
         * Deux stages différents ont des couleurs différentes.
         */
        $palette = [
            '#2563eb',
            '#16a34a',
            '#ea580c',
            '#7c3aed',
            '#0891b2',
            '#db2777',
            '#ca8a04',
            '#4f46e5',
            '#0f766e',
            '#be123c',
            '#6d28d9',
            '#65a30d',
        ];

        $stageId =
            max(
                1,
                (int) (
                    $session->stage_id
                    ?? $session->id
                    ?? 1
                )
            );

        $index =
            ($stageId - 1)
            % count($palette);

        return $palette[$index];
    }

    public function onEventClick(
        EventClickInfo $info,
        Model $event,
        ?string $action = null
    ): void {
        if (! $event instanceof SessionStage) {
            return;
        }

        $this->redirect(
            SessionDetail::getUrl(
                [
                    'session' =>
                        $event->getKey(),
                ],
                panel:
                    'fpsplanificationstage'
            )
        );
    }


    public function getOptions(): array
    {
        return [
            'hiddenDays' =>
                $this->viewMode === 'week'
                    ? [0, 6]
                    : [],
        ];
    }

}
