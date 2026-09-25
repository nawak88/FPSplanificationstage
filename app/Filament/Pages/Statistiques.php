<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;

class Statistiques extends Page
{
    use RequiresAuthentication;

    protected static ?string $navigationLabel =
        'Statistiques';

    protected static string|\UnitEnum|null $navigationGroup =
        'Pilotage';

    protected static ?int $navigationSort =
        10;

    public int $selectedYear;

    public function mount(): void
    {
        $this->selectedYear =
            (int) now()->year;
    }

    public function getTitle(): string
    {
        return 'Statistiques';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousYear')
                ->label('Année précédente')
                ->color('gray')
                ->icon('heroicon-o-chevron-left')
                ->action(fn () => $this->previousYear()),
            Action::make('currentYear')
                ->label('Année actuelle')
                ->action(fn () => $this->currentYear()),
            Action::make('nextYear')
                ->label('Année suivante')
                ->color('gray')
                ->icon('heroicon-o-chevron-right')
                ->iconPosition('after')
                ->action(fn () => $this->nextYear()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $stats = $this->statsData();

        $sections = [];

        foreach ($this->statSections() as $section) {
            $sections[] = Section::make($section['title'])
                ->schema(
                    array_map(
                        fn (array $item): Stat => Stat::make(
                            $item['label'],
                            $item['value']
                        )
                            ->color($this->statColor($item['tone'])),
                        $section['items']
                    )
                )
                ->columns(4)
                ->gridContainer();
        }

        return $schema->components([
            Section::make('Statistiques de l\'année ' . $stats['year'])
                ->description('Les statistiques sont calculées pour l’année sélectionnée. Une session est considérée comme réalisée lorsque sa date de fin est passée et qu’elle n’est pas annulée.')
                ->schema([
                    Select::make('selectedYear')
                        ->label('Année')
                        ->options(array_combine($this->availableYears(), $this->availableYears()))
                        ->native(false)
                        ->live(),
                ]),
            ...$sections,
        ]);
    }

    protected function statColor(string $tone): string
    {
        if (str_contains($tone, 'red')) {
            return 'danger';
        }

        if (str_contains($tone, 'green')) {
            return 'success';
        }

        if (str_contains($tone, 'amber')) {
            return 'warning';
        }

        return 'info';
    }

    public function previousYear(): void
    {
        $this->selectedYear--;
    }

    public function nextYear(): void
    {
        $this->selectedYear++;
    }

    public function currentYear(): void
    {
        $this->selectedYear =
            (int) now()->year;
    }

    public function availableYears(): array
    {
        $currentYear =
            (int) now()->year;

        $firstSession =
            SessionStage::query()
                ->orderBy('debut')
                ->first();

        $lastSession =
            SessionStage::query()
                ->orderByDesc('debut')
                ->first();

        $firstYear =
            $firstSession?->debut
                ?->year
            ?? $currentYear;

        $lastYear =
            $lastSession?->debut
                ?->year
            ?? $currentYear;

        $firstYear =
            min(
                $firstYear,
                $currentYear
            );

        $lastYear =
            max(
                $lastYear,
                $currentYear
            );

        $years = [];

        for (
            $year = $lastYear;
            $year >= $firstYear;
            $year--
        ) {
            $years[] = $year;
        }

        if (
            ! in_array(
                $this->selectedYear,
                $years,
                true
            )
        ) {
            $years[] =
                $this->selectedYear;

            rsort($years);
        }

        return $years;
    }

    public function statSections(): array
    {
        $stats = $this->statsData();

        return [
            [
                'title' => 'Sessions',
                'items' => [
                    [
                        'value' => $stats['sessions_programmees'],
                        'label' => 'Sessions programmées en ' . $stats['year'],
                        'tone' => 'border-l-4 border-blue-500',
                    ],
                    [
                        'value' => $stats['sessions_realisees'],
                        'label' => 'Sessions réalisées',
                        'tone' => 'border-l-4 border-green-500',
                    ],
                    [
                        'value' => $stats['sessions_a_venir'],
                        'label' => 'Sessions à venir',
                        'tone' => 'border-l-4 border-blue-500',
                    ],
                    [
                        'value' => $stats['sessions_annulees'],
                        'label' => 'Sessions annulées',
                        'tone' => 'border-l-4 border-red-500',
                    ],
                    [
                        'value' => $stats['sessions_en_cours'],
                        'label' => 'Sessions actuellement en cours',
                        'tone' => 'border-l-4 border-amber-500',
                    ],
                ],
            ],
            [
                'title' => 'Stagiaires et remplissage',
                'items' => [
                    [
                        'value' => $stats['stagiaires_reserves'],
                        'label' => 'Places réservées',
                        'tone' => 'border-l-4 border-blue-500',
                    ],
                    [
                        'value' => $stats['stagiaires_confirmes'],
                        'label' => 'Stagiaires confirmés',
                        'tone' => 'border-l-4 border-green-500',
                    ],
                    [
                        'value' => $stats['liste_attente'],
                        'label' => 'En liste d’attente',
                        'tone' => 'border-l-4 border-amber-500',
                    ],
                    [
                        'value' => $stats['capacite_totale'],
                        'label' => 'Capacité totale programmée',
                        'tone' => 'border-l-4 border-slate-500',
                    ],
                    [
                        'value' => $stats['places_occupees'],
                        'label' => 'Places occupées',
                        'tone' => 'border-l-4 border-blue-500',
                    ],
                    [
                        'value' => $stats['places_restantes'],
                        'label' => 'Places encore disponibles',
                        'tone' => 'border-l-4 border-slate-500',
                    ],
                    [
                        'value' => $stats['taux_remplissage'] . ' %',
                        'label' => 'Taux de remplissage',
                        'tone' => 'border-l-4 border-green-500',
                    ],
                ],
            ],
            [
                'title' => 'Besoins de formation',
                'items' => [
                    [
                        'value' => $stats['besoins_total'],
                        'label' => 'Besoins reçus en ' . $stats['year'],
                        'tone' => 'border-l-4 border-slate-500',
                    ],
                    [
                        'value' => $stats['besoins_a_planifier'],
                        'label' => 'Besoins à planifier',
                        'tone' => 'border-l-4 border-amber-500',
                    ],
                    [
                        'value' => $stats['besoins_planifies'],
                        'label' => 'Besoins planifiés',
                        'tone' => 'border-l-4 border-green-500',
                    ],
                ],
            ],
        ];
    }

    public function statsData(): array
    {
        $yearStart =
            Carbon::create(
                $this->selectedYear,
                1,
                1,
                0,
                0,
                0
            )
                ->startOfDay();

        $yearEnd =
            $yearStart
                ->copy()
                ->endOfYear()
                ->endOfDay();

        $sessions =
            SessionStage::query()
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
                ->whereBetween(
                    'debut',
                    [
                        $yearStart,
                        $yearEnd,
                    ]
                )
                ->get();

        $sessionsAnnulees =
            $sessions
                ->where(
                    'statut',
                    'annulee'
                );

        $sessionsNonAnnulees =
            $sessions
                ->where(
                    'statut',
                    '!=',
                    'annulee'
                );

        $maintenant =
            now();

        $sessionsRealisees =
            $sessionsNonAnnulees
                ->filter(
                    fn (
                        SessionStage $session
                    ): bool =>
                        $session->fin !== null
                        && $session->fin->lt(
                            $maintenant
                        )
                );

        $sessionsAVenir =
            $sessionsNonAnnulees
                ->filter(
                    fn (
                        SessionStage $session
                    ): bool =>
                        $session->debut !== null
                        && $session->debut->gte(
                            $maintenant
                        )
                );

        $sessionsEnCours =
            $sessionsNonAnnulees
                ->filter(
                    fn (
                        SessionStage $session
                    ): bool =>
                        $session->debut !== null
                        && $session->fin !== null
                        && $session->debut->lte(
                            $maintenant
                        )
                        && $session->fin->gte(
                            $maintenant
                        )
                );

        $capaciteTotale =
            $sessionsNonAnnulees
                ->whereNotNull(
                    'capacite_max'
                )
                ->sum(
                    'capacite_max'
                );

        $placesOccupees =
            $sessionsNonAnnulees
                ->sum(
                    fn (
                        SessionStage $session
                    ): int =>
                        (int)
                        $session
                            ->participants_count
                );

        $placesRestantes =
            $sessionsNonAnnulees
                ->sum(
                    fn (
                        SessionStage $session
                    ): int =>
                        $session
                            ->capacite_max
                            !== null
                        ? max(
                            0,
                            (int)
                                $session
                                    ->capacite_max
                            - (int)
                                $session
                                    ->participants_count
                        )
                        : 0
                );

        $tauxRemplissage =
            $capaciteTotale > 0
                ? round(
                    (
                        $placesOccupees
                        / $capaciteTotale
                    ) * 100,
                    1
                )
                : 0;

        $inscriptionsBase =
            Inscription::query()
                ->whereHas(
                    'sessionStage',
                    fn ($query) =>
                        $query->whereBetween(
                            'debut',
                            [
                                $yearStart,
                                $yearEnd,
                            ]
                        )
                );

        $stagiairesConfirmes =
            (clone $inscriptionsBase)
                ->where(
                    'statut',
                    'confirmee'
                )
                ->count();

        $stagiairesReserves =
            (clone $inscriptionsBase)
                ->whereIn(
                    'statut',
                    [
                        'attente_nemo',
                        'confirmee',
                        'attente_derogation',
                    ]
                )
                ->count();

        $listeAttente =
            (clone $inscriptionsBase)
                ->where(
                    'statut',
                    'liste_attente'
                )
                ->count();

        $besoinsTotal =
            BesoinFormation::query()
                ->whereYear(
                    'created_at',
                    $this->selectedYear
                )
                ->count();

        $besoinsAPlanifier =
            BesoinFormation::query()
                ->whereYear(
                    'created_at',
                    $this->selectedYear
                )
                ->where(
                    'statut',
                    'a_planifier'
                )
                ->count();

        $besoinsPlanifies =
            BesoinFormation::query()
                ->whereYear(
                    'created_at',
                    $this->selectedYear
                )
                ->where(
                    'statut',
                    'planifie'
                )
                ->count();

        return [
            'year' =>
                $this->selectedYear,

            'sessions_programmees' =>
                $sessionsNonAnnulees
                    ->count(),

            'sessions_realisees' =>
                $sessionsRealisees
                    ->count(),

            'sessions_a_venir' =>
                $sessionsAVenir
                    ->count(),

            'sessions_en_cours' =>
                $sessionsEnCours
                    ->count(),

            'sessions_annulees' =>
                $sessionsAnnulees
                    ->count(),

            'stagiaires_reserves' =>
                $stagiairesReserves,

            'stagiaires_confirmes' =>
                $stagiairesConfirmes,

            'liste_attente' =>
                $listeAttente,

            'capacite_totale' =>
                $capaciteTotale,

            'places_occupees' =>
                $placesOccupees,

            'places_restantes' =>
                $placesRestantes,

            'taux_remplissage' =>
                $tauxRemplissage,

            'besoins_total' =>
                $besoinsTotal,

            'besoins_a_planifier' =>
                $besoinsAPlanifier,

            'besoins_planifies' =>
                $besoinsPlanifies,
        ];
    }
}
