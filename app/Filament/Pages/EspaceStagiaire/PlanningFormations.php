<?php

namespace Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinNouveau;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuiviRecherche;
use Modules\FPSplanificationstage\Filament\Widgets\PlanningCalendar;

class PlanningFormations extends Page
{
    protected static ?string $navigationLabel =
        'Planning des formations';

    protected static string|\UnitEnum|null $navigationGroup =
        'Espace stagiaire';

    protected static ?int $navigationSort =
        10000;

    protected static ?string $slug =
        'espace-stagiaire/planning-formations';

    public string $searchTerm = '';

    public string $viewMode = 'month';

    public function getTitle(): string
    {
        return 'Planning des formations';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exprimerBesoin')
                ->label('Exprimer un besoin de stage')
                ->icon('heroicon-o-plus-circle')
                ->url(
                    BesoinNouveau::getUrl(
                        panel:
                            'fpsplanificationstage'
                    )
                )
                ->color('success'),
            Action::make('suivreBesoin')
                ->label('Suivre un besoin')
                ->icon('heroicon-o-magnifying-glass')
                ->url(
                    BesoinSuiviRecherche::getUrl(
                        panel:
                            'fpsplanificationstage'
                    )
                )
                ->color('gray'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['lg' => 3])
                ->schema([
                    Section::make('Vous souhaitez vous inscrire ?')
                        ->description('Choisissez directement une session disponible dans le calendrier ci-dessous.')
                        ->compact(),
                    Section::make('Aucune session ne correspond à votre besoin ?')
                        ->description('Votre bâtiment ou votre unité peut transmettre directement une expression de besoin.')
                        ->compact(),
                    Section::make('Vous avez déjà exprimé un besoin ?')
                        ->description('Utilisez votre référence BES-xxxxxx et votre adresse e-mail pour suivre son avancement.')
                        ->compact(),
                ]),
            Section::make('Recherche')
                ->schema([
                    Grid::make(['lg' => 2])
                        ->schema([
                            TextInput::make('searchTerm')
                                ->label('Rechercher')
                                ->placeholder('Nom de formation, lieu, service...')
                                ->live(onBlur: false)
                                ->debounce(300),
                            Select::make('viewMode')
                                ->label('Vue')
                                ->options([
                                    'month' => 'Mois',
                                    'week' => 'Semaine',
                                    'list' => 'Liste',
                                ])
                                ->native(false)
                                ->default('month')
                                ->live(),
                        ]),
                ]),
            Section::make('Calendrier')
                ->schema([
                    Livewire::make(PlanningCalendar::class, [
                        'stageFilter' => '',
                        'instructeurFilter' => '',
                        'salleFilter' => '',
                        'statutFilter' => '',
                        'searchTerm' => $this->searchTerm,
                        'viewMode' => $this->viewMode,
                    ])
                        ->key('planning-formations-calendar-' . md5($this->searchTerm . '-' . $this->viewMode)),
                ]),
        ]);
    }
}
