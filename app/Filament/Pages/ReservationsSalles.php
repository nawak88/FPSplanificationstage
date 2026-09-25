<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SalleOccupation;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\SalleReservationWorkbookService;
use Throwable;

class ReservationsSalles extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresAuthentication;

    protected static ?string $navigationLabel =
        'Réservations salles';

    protected static string|\UnitEnum|null $navigationGroup =
        'Planification';

    protected static ?int $navigationSort = 35;

    public string $salleFilter = '';

    public string $dateFilter = '';

    public function getTitle(): string
    {
        return 'Réservations de salles';
    }

    public function content(Schema $schema): Schema
    {
        $summary = $this->summary();
        $master = $summary['master'] ?? [];

        return $schema->components([
            Section::make('Vue d’ensemble')
                ->description('Le fichier Excel / SharePoint reste le fichier maître. Les réservations importées bloquent les salles dans Skeletor.')
                ->schema([
                    Grid::make(['md' => 2, 'xl' => 5])
                        ->schema([
                            Stat::make('Fichier maître', $master['original_name'] ?? 'Aucun')
                                ->description(filled($master['imported_at'] ?? null)
                                    ? 'Importé le ' . \Illuminate\Support\Facades\Date::parse($master['imported_at'])->format('d/m/Y H:i')
                                    : 'Aucun import enregistré.'),
                            Stat::make('Année', (string) ($master['year'] ?? '—'))
                                ->description('Calendrier Excel actif.'),
                            Stat::make('Salles actives', (string) ($summary['salles'] ?? 0))
                                ->description((string) ($summary['sans_capacite'] ?? 0) . ' sans capacité renseignée.'),
                            Stat::make('Occupations Excel', (string) ($summary['occupations_excel'] ?? 0))
                                ->description('Créneaux bloquants importés.'),
                            Stat::make('Sessions Skeletor', (string) ($summary['sessions_skeletor'] ?? 0))
                                ->description('Sessions avec une salle.'),
                        ]),
                ]),

            Section::make('Occupations importées depuis Excel')
                ->description($this->filteredOccupationsCount() . ' résultat(s). Les 250 premiers sont affichés.')
                ->schema([
                    Grid::make(['md' => 3])
                        ->schema([
                            Select::make('salleFilter')
                                ->label('Salle')
                                ->options(
                                    $this->roomOptions()->mapWithKeys(
                                        fn (Salle $room): array => [
                                            (string) $room->id => $room->code
                                                ? $room->code . ' — ' . $room->nom
                                                : $room->nom,
                                        ]
                                    )->all()
                                )
                                ->placeholder('Toutes')
                                ->native(false)
                                ->live(),
                            DatePicker::make('dateFilter')
                                ->label('Date')
                                ->native(false)
                                ->live(),
                            Action::make('resetFilters')
                                ->label('Réinitialiser')
                                ->color('gray')
                                ->icon('heroicon-o-arrow-path')
                                ->action(fn () => $this->resetFilters())
                                ->visible(fn (): bool => filled($this->salleFilter) || filled($this->dateFilter)),
                        ]),
                    EmbeddedTable::make(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->occupations())
            ->columns([
                TextColumn::make('salle.code')
                    ->label('Salle')
                    ->formatStateUsing(fn ($state, $record): string => $record->salle?->code ?: $record->salle?->nom ?: '—'),
                TextColumn::make('debut')
                    ->label('Date')
                    ->date('d/m/Y'),
                TextColumn::make('debut')
                    ->label('Horaire')
                    ->formatStateUsing(function ($state, $record): string {
                        if (! $record->debut) {
                            return '—';
                        }

                        $fin = $record->fin ? $record->fin->format('H:i') : '—';

                        return $record->debut->format('H:i') . ' → ' . $fin;
                    }),
                TextColumn::make('libelle')
                    ->label('Libellé'),
                TextColumn::make('excel_range')
                    ->label('Origine Excel')
                    ->formatStateUsing(fn ($state): string => $state ?: 'Excel'),
            ])
            ->emptyStateHeading('Aucune occupation ne correspond aux filtres.')
            ->paginated([25]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make(
                'importReservationsSalles'
            )
                ->label(
                    'Importer Excel'
                )
                ->icon(
                    'heroicon-o-arrow-up-tray'
                )
                ->color('primary')
                ->modalHeading(
                    'Importer le fichier de réservation des salles'
                )
                ->modalDescription(
                    'Le fichier importé devient le fichier maître. '
                    . 'Les réservations déjà présentes seront utilisées '
                    . 'comme indisponibilités de salles.'
                )
                ->modalSubmitActionLabel(
                    'Importer'
                )
                ->schema([
                    FileUpload::make(
                        'fichier'
                    )
                        ->label(
                            'Fichier de réservation'
                        )
                        ->disk('local')
                        ->directory(
                            'imports/reservations-salles'
                        )
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(20480)
                        ->preserveFilenames()
                        ->required(),
                ])
                ->action(
                    function (
                        array $data
                    ): void {
                        $uploadedPath =
                            $data[
                                'fichier'
                            ]
                            ?? null;

                        if (
                            ! is_string(
                                $uploadedPath
                            )
                            || $uploadedPath === ''
                        ) {
                            Notification::make()
                                ->title(
                                    'Import impossible'
                                )
                                ->body(
                                    'Aucun fichier valide n’a été sélectionné.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $absolutePath =
                                Storage::disk(
                                    'local'
                                )
                                    ->path(
                                        $uploadedPath
                                    );

                            $result =
                                app(
                                    SalleReservationWorkbookService::class
                                )
                                    ->import(
                                        $absolutePath,
                                        basename(
                                            $uploadedPath
                                        )
                                    );

                            Notification::make()
                                ->title(
                                    'Réservations importées'
                                )
                                ->body(
                                    $result['salles']
                                    . ' salle(s) • '
                                    . $result['occupations']
                                    . ' occupation(s) • année '
                                    . $result['annee']
                                )
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (
                            Throwable $exception
                        ) {
                            Notification::make()
                                ->title(
                                    'Erreur pendant l’import'
                                )
                                ->body(
                                    $exception->getMessage()
                                )
                                ->danger()
                                ->persistent()
                                ->send();
                        } finally {
                            if (
                                is_string(
                                    $uploadedPath
                                )
                                && $uploadedPath !== ''
                            ) {
                                Storage::disk(
                                    'local'
                                )
                                    ->delete(
                                        $uploadedPath
                                    );
                            }
                        }
                    }
                ),

            Action::make(
                'exportReservationsSalles'
            )
                ->label(
                    'Exporter Excel'
                )
                ->icon(
                    'heroicon-o-arrow-down-tray'
                )
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(
                    'Exporter le planning des salles ?'
                )
                ->modalDescription(
                    'Skeletor repart du fichier maître importé, conserve '
                    . 'les réservations existantes et ajoute les sessions '
                    . 'planifiées. En cas de conflit avec une réservation '
                    . 'déjà présente, l’export est refusé.'
                )
                ->modalSubmitActionLabel(
                    'Générer le fichier'
                )
                ->action(
                    function () {
                        try {
                            $path = app(SalleReservationWorkbookService::class)->export();

                            return response()
                                ->download(
                                    $path,
                                    'resa salle - Skeletor - '
                                    . now()->format('Ymd-His')
                                    . '.xlsx'
                                )
                                ->deleteFileAfterSend(true);
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title(
                                    'Export impossible'
                                )
                                ->body(
                                    $exception->getMessage()
                                )
                                ->danger()
                                ->persistent()
                                ->send();

                            return null;
                        }
                    }
                ),
        ];
    }

    public function resetFilters(): void
    {
        $this->salleFilter = '';
        $this->dateFilter = '';
    }

    public function masterInfo(): array
    {
        $disk = Storage::disk('local');
        $path = 'fpsplanificationstage/salles/master.json';

        if (! $disk->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function summary(): array
    {
        $master = $this->masterInfo();
        $year = isset($master['year']) ? (int) $master['year'] : null;

        $sessionsQuery = SessionStage::query()
            ->whereNotNull('salle_id')
            ->whereIn('statut', ['planifiee', 'confirmee']);

        if ($year) {
            $sessionsQuery->whereYear('debut', $year);
        }

        return [
            'master' => $master,
            'salles' => Salle::query()->where('actif', true)->count(),
            'sans_capacite' => Salle::query()->where('actif', true)->whereNull('capacite')->count(),
            'occupations_excel' => SalleOccupation::query()->where('source', 'excel_sharepoint')->count(),
            'sessions_skeletor' => $sessionsQuery->count(),
        ];
    }

    public function roomOptions(): Collection
    {
        return Salle::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'code', 'nom', 'capacite']);
    }

    public function filteredOccupationsCount(): int
    {
        return $this->occupationsQuery()->count();
    }

    public function occupations(): Collection
    {
        return $this->occupationsQuery()
            ->with('salle')
            ->orderBy('debut')
            ->limit(250)
            ->get();
    }

    public function skeletorSessions(): Collection
    {
        $master = $this->masterInfo();
        $year = isset($master['year']) ? (int) $master['year'] : null;

        $query = SessionStage::query()
            ->with(['stage', 'salle'])
            ->whereNotNull('salle_id')
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->orderBy('debut');

        if ($year) {
            $query->whereYear('debut', $year);
        }

        return $query->limit(100)->get();
    }

    private function occupationsQuery()
    {
        return SalleOccupation::query()
            ->where('source', 'excel_sharepoint')
            ->when(
                $this->salleFilter !== '',
                fn ($query) => $query->where('salle_id', (int) $this->salleFilter)
            )
            ->when(
                $this->dateFilter !== '',
                fn ($query) => $query->whereDate('debut', $this->dateFilter)
            );
    }
}
