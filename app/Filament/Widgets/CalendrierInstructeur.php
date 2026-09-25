<?php

namespace Modules\FPSplanificationstage\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\DateSelectInfo;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Model;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\IndisponibiliteInstructeurService;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;

class CalendrierInstructeur extends CalendarWidget
{
    protected string|\Illuminate\Support\HtmlString|null|bool $heading =
        'Mon planning et mes indisponibilités';

    protected bool $dateSelectEnabled = true;

    protected bool $eventClickEnabled = true;

    public ?string $selectionDateDebut = null;

    public ?string $selectionHeureDebut = null;

    public ?string $selectionDateFin = null;

    public ?string $selectionHeureFin = null;

    public bool $selectionJourneeEntiere = true;

    public ?int $indisponibiliteSelectionnee = null;

    public function getCalendarView(): CalendarViewType
    {
        return CalendarViewType::DayGridMonth;
    }

    protected function getEvents(
        FetchInfo $info
    ): array {
        $instructeur = app(
            InstructeurConnecteService::class
        )->resolve();

        if (! $instructeur) {
            return [];
        }

        $sessions = SessionStage::query()
            ->with('stage')
            ->whereHas(
                'instructeurs',
                fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where(
                        'rh_marins.id',
                        $instructeur->getKey()
                    )
            )
            ->where('statut', '<>', 'annulee')
            ->where('debut', '<=', $info->end)
            ->where('fin', '>=', $info->start)
            ->get()
            ->map(
                fn (SessionStage $session): CalendarEvent =>
                    CalendarEvent::make($session)
                        ->title(
                            'Stage — '
                            . (
                                $session->stage?->libelle_court
                                ?? $session->code_session
                            )
                        )
                        ->start($session->debut)
                        ->end($session->fin)
                        ->backgroundColor('#2563eb')
                        ->textColor('#ffffff')
                        ->editable(false)
            );

        $service = app(
            IndisponibiliteInstructeurService::class
        );

        $indisponibilites =
            IndisponibiliteInstructeur::query()
                ->where(
                    'instructeur_id',
                    $instructeur->getKey()
                )
                ->where('actif', true)
                ->whereDate(
                    'date_debut',
                    '<=',
                    $info->end->toDateString()
                )
                ->whereDate(
                    'date_fin',
                    '>=',
                    $info->start->toDateString()
                )
                ->get()
                ->map(
                    function (
                        IndisponibiliteInstructeur $indisponibilite
                    ) use ($service): CalendarEvent {
                        $event = CalendarEvent::make(
                            $indisponibilite
                        )
                            ->title(
                                'Indisponible'
                                . (
                                    $indisponibilite->motif
                                        ? ' — '
                                            . $this->motifLabel(
                                                $indisponibilite->motif
                                            )
                                        : ''
                                )
                            )
                            ->start(
                                $service->debut(
                                    $indisponibilite
                                )
                            )
                            ->backgroundColor('#dc2626')
                            ->textColor('#ffffff')
                            ->editable(false);

                        if ($indisponibilite->journee_entiere) {
                            return $event
                                ->end(
                                    $indisponibilite->date_fin
                                        ->copy()
                                        ->addDay()
                                )
                                ->allDay();
                        }

                        return $event->end(
                            $service->fin(
                                $indisponibilite
                            )
                        );
                    }
                );

        return $sessions
            ->concat($indisponibilites)
            ->values()
            ->all();
    }

    protected function onDateSelect(
        DateSelectInfo $info
    ): void {
        $this->selectionJourneeEntiere =
            $info->allDay;

        $this->selectionDateDebut =
            $info->start->toDateString();

        $this->selectionDateFin =
            ($info->allDay
                ? $info->end->subDay()
                : $info->end
            )->toDateString();

        $this->selectionHeureDebut =
            $info->allDay
                ? null
                : $info->start->format('H:i');

        $this->selectionHeureFin =
            $info->allDay
                ? null
                : $info->end->format('H:i');

        $this->mountAction(
            'declarerIndisponibilite'
        );
    }

    public function declarerIndisponibiliteAction(): Action
    {
        return Action::make(
            'declarerIndisponibilite'
        )
            ->label('Me déclarer indisponible')
            ->modalHeading('Déclarer une indisponibilité')
            ->modalDescription(
                'La période sélectionnée sera ajoutée à votre calendrier et signalée aux gestionnaires de stage.'
            )
            ->modalSubmitActionLabel(
                'Enregistrer l’indisponibilité'
            )
            ->fillForm(fn (): array => [
                'indisponible' => true,
                'journee_entiere' =>
                    $this->selectionJourneeEntiere,
                'date_debut' =>
                    $this->selectionDateDebut,
                'heure_debut' =>
                    $this->selectionHeureDebut,
                'date_fin' =>
                    $this->selectionDateFin,
                'heure_fin' =>
                    $this->selectionHeureFin,
            ])
            ->schema([
                Toggle::make('indisponible')
                    ->label('Indisponible')
                    ->default(true)
                    ->accepted()
                    ->helperText(
                        'Cette case doit être cochée pour confirmer la déclaration.'
                    ),

                Toggle::make('journee_entiere')
                    ->label('Journée entière')
                    ->default(true)
                    ->live(),

                DatePicker::make('date_debut')
                    ->label('Date de début')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),

                DatePicker::make('date_fin')
                    ->label('Date de fin')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->rules([
                        'after_or_equal:date_debut',
                    ]),

                TimePicker::make('heure_debut')
                    ->label('Heure de début')
                    ->seconds(false)
                    ->native(false)
                    ->visible(
                        fn ($get): bool =>
                            ! (bool) $get('journee_entiere')
                    )
                    ->required(
                        fn ($get): bool =>
                            ! (bool) $get('journee_entiere')
                    ),

                TimePicker::make('heure_fin')
                    ->label('Heure de fin')
                    ->seconds(false)
                    ->native(false)
                    ->visible(
                        fn ($get): bool =>
                            ! (bool) $get('journee_entiere')
                    )
                    ->required(
                        fn ($get): bool =>
                            ! (bool) $get('journee_entiere')
                    ),

                Select::make('motif')
                    ->label('Motif')
                    ->options([
                        'conge' => 'Congé',
                        'mission' => 'Mission',
                        'formation' => 'Formation',
                        'service' => 'Service',
                        'absence' => 'Absence',
                        'autre' => 'Autre',
                    ]),

                Textarea::make('commentaire')
                    ->label('Commentaire')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(
                function (array $data): void {
                    $instructeur = app(
                        InstructeurConnecteService::class
                    )->resolve();

                    abort_unless(
                        $instructeur,
                        403
                    );

                    $resultat = app(
                        IndisponibiliteInstructeurService::class
                    )->creer(
                        $instructeur,
                        $data
                    );

                    $conflits =
                        $resultat['conflits'];

                    $notification = Notification::make()
                        ->title(
                            'Indisponibilité enregistrée'
                        )
                        ->body(
                            $conflits->isEmpty()
                                ? 'Aucun conflit avec vos sessions planifiées.'
                                : $conflits->count()
                                    . ' session(s) planifiée(s) sont en conflit. Les gestionnaires ont été alertés.'
                        );

                    if ($conflits->isEmpty()) {
                        $notification->success();
                    } else {
                        $notification->warning();
                    }

                    $notification->send();

                    $this->refreshRecords();
                }
            );
    }

    public function supprimerIndisponibiliteAction(): Action
    {
        return Action::make(
            'supprimerIndisponibilite'
        )
            ->label('Supprimer mon indisponibilité')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer cette indisponibilité ?')
            ->action(function (): void {
                $instructeur = app(
                    InstructeurConnecteService::class
                )->resolve();

                abort_unless(
                    $instructeur
                    && $this->indisponibiliteSelectionnee,
                    403
                );

                IndisponibiliteInstructeur::query()
                    ->whereKey(
                        $this->indisponibiliteSelectionnee
                    )
                    ->where(
                        'instructeur_id',
                        $instructeur->getKey()
                    )
                    ->firstOrFail()
                    ->delete();

                $this->indisponibiliteSelectionnee = null;

                Notification::make()
                    ->title('Indisponibilité supprimée')
                    ->success()
                    ->send();

                $this->refreshRecords();
            });
    }

    public function onEventClick(
        EventClickInfo $info,
        Model $event,
        ?string $action = null
    ): void {
        if ($event instanceof IndisponibiliteInstructeur) {
            $this->indisponibiliteSelectionnee =
                (int) $event->getKey();

            $this->mountAction(
                'supprimerIndisponibilite'
            );

            return;
        }

        if ($event instanceof SessionStage) {
            $this->redirect(
                SessionDetail::getUrl(
                    ['session' => $event->getKey()],
                    panel: 'fpsplanificationstage'
                )
            );
        }
    }

    public function getOptions(): array
    {
        return [
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' =>
                    'dayGridMonth,timeGridWeek,listMonth',
            ],
            'slotMinTime' => '07:00:00',
            'slotMaxTime' => '19:00:00',
            'selectMirror' => true,
            'nowIndicator' => true,
        ];
    }

    private function motifLabel(
        string $motif
    ): string {
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
}
