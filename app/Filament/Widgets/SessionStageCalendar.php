<?php

namespace Modules\FPSplanificationstage\Filament\Widgets;

use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Illuminate\Database\Eloquent\Model;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Models\SessionStage;

class SessionStageCalendar extends PlanningCalendar
{
    protected bool $dateClickEnabled = true;

    protected bool $hideCancelledByDefault = true;

    public function getCalendarView(): CalendarViewType
    {
        return match ($this->viewMode) {
            'week' => CalendarViewType::TimeGridWeek,
            'list' => CalendarViewType::ListMonth,
            default => CalendarViewType::DayGridMonth,
        };
    }

    public function onEventClick(
        EventClickInfo $info,
        Model $event,
        ?string $action = null
    ): void {
        if (! $event instanceof SessionStage) {
            return;
        }

        $this->redirect(SessionStageResource::getUrl('edit', [
            'record' => $event->getKey(),
        ]));
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        if (! SessionStageResource::canCreate()) {
            return;
        }

        $parameters = [
            'date' => $info->date->format('Y-m-d'),
        ];

        if (! $info->allDay) {
            $hour = min(15, max(8, $info->date->hour));
            $parameters['heure'] = sprintf('%02d:00', $hour);
        }

        $this->redirect(
            SessionStageResource::getUrl('create', $parameters)
        );
    }

    public function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '16:00:00',
            'allDaySlot' => false,
            'nowIndicator' => true,
        ];
    }
}
