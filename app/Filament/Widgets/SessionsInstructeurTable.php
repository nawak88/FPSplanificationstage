<?php

namespace Modules\FPSplanificationstage\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;

class SessionsInstructeurTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Mes prochaines sessions et leurs stagiaires')
            ->description(
                'Les candidatures refusées ou annulées ne sont pas affichées.'
            )
            ->query($this->sessionsQuery())
            ->columns([
                TextColumn::make('stage.libelle_court')
                    ->label('Stage')
                    ->description(
                        fn (SessionStage $record): ?string =>
                            $record->code_session
                    )
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('debut')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fin')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('salle.nom')
                    ->label('Salle')
                    ->placeholder('Non affectée'),

                TextColumn::make('stagiaires_inscrits')
                    ->label('Stagiaires inscrits')
                    ->state(
                        fn (SessionStage $record): array =>
                            $record->inscriptions
                                ->map(
                                    fn ($inscription): string =>
                                        $inscription->nom_complet
                                )
                                ->filter()
                                ->values()
                                ->all()
                    )
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->limitList(5)
                    ->placeholder('Aucun stagiaire'),

                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            match ($state) {
                                'planifiee' => 'Planifiée',
                                'confirmee' => 'Confirmée',
                                default => $state ?? '—',
                            }
                    )
                    ->color(
                        fn (?string $state): string =>
                            $state === 'confirmee'
                                ? 'success'
                                : 'info'
                    ),
            ])
            ->defaultSort('debut')
            ->recordUrl(
                fn (SessionStage $record): string =>
                    SessionDetail::getUrl(
                        ['session' => $record->getKey()],
                        panel: 'fpsplanificationstage'
                    )
            )
            ->emptyStateHeading('Aucune session à venir')
            ->paginated([5, 10, 25]);
    }

    private function sessionsQuery(): Builder
    {
        $instructeur = app(
            InstructeurConnecteService::class
        )->resolve();

        return SessionStage::query()
            ->with([
                'stage',
                'salle',
                'inscriptions' =>
                    fn ($query) => $query
                        ->whereNotIn(
                            'statut',
                            ['refusee', 'annulee']
                        )
                        ->orderBy('candidat_nom')
                        ->orderBy('candidat_prenom'),
            ])
            ->when(
                $instructeur,
                fn (Builder $query) => $query->whereHas(
                    'instructeurs',
                    fn ($instructeurQuery) =>
                        $instructeurQuery
                            ->withoutGlobalScopes()
                            ->where(
                                'rh_marins.id',
                                $instructeur->getKey()
                            )
                ),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->whereIn(
                'statut',
                ['planifiee', 'confirmee']
            )
            ->where('fin', '>=', now());
    }
}
