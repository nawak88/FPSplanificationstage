<?php

namespace Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Filament\Widgets\SessionStageCalendar;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;

class PlanningSessionStages extends Page
{
    protected static string $resource = SessionStageResource::class;

    public string $stageFilter = '';

    public string $instructeurFilter = '';

    public string $salleFilter = '';

    public string $statutFilter = '';

    public string $viewMode = 'month';

    public static function canAccess(array $parameters = []): bool
    {
        return SessionStageResource::canAccess()
            && parent::canAccess($parameters);
    }

    public function getTitle(): string
    {
        return 'Planning / Calendrier';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetFilters')
                ->label('Réinitialiser les filtres')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->resetFilters())
                ->visible(fn (): bool => $this->hasActiveFilters()),
            CreateAction::make()
                ->label('Créer une session'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtres')
                ->compact()
                ->schema([
                    Grid::make([
                        'md' => 2,
                        'xl' => 5,
                    ])->schema([
                        Select::make('stageFilter')
                            ->label('Stage')
                            ->placeholder('Tous les stages')
                            ->options(fn (): array => $this->stageOptions())
                            ->searchable()
                            ->live(),
                        Select::make('instructeurFilter')
                            ->label('Instructeur')
                            ->placeholder('Tous les instructeurs')
                            ->options(fn (): array => $this->instructorOptions())
                            ->searchable()
                            ->live(),
                        Select::make('salleFilter')
                            ->label('Salle')
                            ->placeholder('Toutes les salles')
                            ->options(fn (): array => $this->roomOptions())
                            ->searchable()
                            ->live(),
                        Select::make('statutFilter')
                            ->label('Statut')
                            ->placeholder('Tous sauf annulées')
                            ->options([
                                'brouillon' => 'Brouillon',
                                'planifiee' => 'Planifiée',
                                'confirmee' => 'Confirmée',
                                'annulee' => 'Annulée',
                                'terminee' => 'Terminée',
                            ])
                            ->live(),
                        Select::make('viewMode')
                            ->label('Vue')
                            ->options([
                                'month' => 'Mois',
                                'week' => 'Semaine',
                                'list' => 'Liste',
                            ])
                            ->native(false)
                            ->live(),
                    ]),
                ]),
            Section::make('Calendrier')
                ->schema([
                    Livewire::make(SessionStageCalendar::class, [
                        'stageFilter' => $this->stageFilter,
                        'instructeurFilter' => $this->instructeurFilter,
                        'salleFilter' => $this->salleFilter,
                        'statutFilter' => $this->statutFilter,
                        'viewMode' => $this->viewMode,
                    ])->key('session-stage-calendar-' . $this->filterKey()),
                ]),
        ]);
    }

    public function resetFilters(): void
    {
        $this->stageFilter = '';
        $this->instructeurFilter = '';
        $this->salleFilter = '';
        $this->statutFilter = '';
        $this->viewMode = 'month';
    }

    private function hasActiveFilters(): bool
    {
        return $this->filterKey() !== md5('||||month');
    }

    private function filterKey(): string
    {
        return md5(implode('|', [
            $this->stageFilter,
            $this->instructeurFilter,
            $this->salleFilter,
            $this->statutFilter,
            $this->viewMode,
        ]));
    }

    private function stageOptions(): array
    {
        return Stage::query()
            ->where('actif', true)
            ->orderBy('libelle_court')
            ->get()
            ->mapWithKeys(fn (Stage $stage): array => [
                $stage->getKey() => trim(
                    ($stage->code_stage ? $stage->code_stage . ' — ' : '')
                    . $stage->libelle_court
                ),
            ])
            ->all();
    }

    private function instructorOptions(): array
    {
        return Marin::query()
            ->whereIn(
                'rh_marins.id',
                SessionStage::query()
                    ->join(
                        'instructeur_session_stage',
                        'session_stages.id',
                        '=',
                        'instructeur_session_stage.session_stage_id'
                    )
                    ->select('instructeur_session_stage.instructeur_id')
            )
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->mapWithKeys(fn (Marin $marin): array => [
                $marin->getKey() => trim(
                    mb_strtoupper($marin->nom) . ' ' . $marin->prenom
                ),
            ])
            ->all();
    }

    private function roomOptions(): array
    {
        return Salle::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->get()
            ->mapWithKeys(fn (Salle $salle): array => [
                $salle->getKey() => trim(
                    ($salle->code ? $salle->code . ' — ' : '')
                    . $salle->nom
                ),
            ])
            ->all();
    }
}
