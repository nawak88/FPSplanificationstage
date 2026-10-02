<?php

namespace Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Services\BesoinFormationGroupedPlanner;

class BesoinFormationsTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            /*
             * BESOINS_OPERATIONNELS_MULTI_SESSIONS
             *
             * Cette page est une file de travail :
             * - À planifier
             * - Conflit
             *
             * Les besoins planifiés et annulés restent en base
             * pour la traçabilité et les statistiques, mais ne
             * sont plus affichés dans cette liste opérationnelle.
             */
            ->modifyQueryUsing(
                fn ($query) =>
                    $query->whereIn(
                        'statut',
                        [
                            'a_planifier',
                            'partiellement_planifie',
                            'conflit',
                        ]
                    )
            )
            ->columns([

                TextColumn::make(
                    'code_besoin'
                )
                    ->label('Besoin')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make(
                    'demandeur'
                )
                    ->label(
                        'Bâtiment / unité'
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make(
                    'stage.libelle_court'
                )
                    ->label('Stage')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make(
                    'type_periode'
                )
                    ->label('Période')
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string =>
                            match ($state) {
                                'dates_fixes' =>
                                    'Date de début imposée',

                                'plage' =>
                                    'Période disponible',

                                'plage_demarrage' =>
                                    'Plage de démarrage',
                                'indisponibilite' => 'Période d’indisponibilité',

                                default =>
                                    $state ?? '—',
                            }
                    ),

                TextColumn::make(
                    'date_debut_souhaitee'
                )
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make(
                    'date_fin_souhaitee'
                )
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make(
                    'nombre_stagiaires'
                )
                    ->label('Besoin estimé')
                    ->suffix(' pers.')
                    ->placeholder('—'),
                TextColumn::make(
                    'effectif_planifie'
                )
                    ->label('Planifié')
                    ->badge()
                    ->color('info'),

                TextColumn::make(
                    'effectif_restant'
                )
                    ->label('Reste')
                    ->badge()
                    ->color(
                        fn (
                            mixed $state
                        ): string =>
                            (int) $state > 0
                                ? 'warning'
                                : 'success'
                    ),

                TextColumn::make(
                    'statut'
                )
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string =>
                            match ($state) {
                                'a_planifier' =>
                                    'À planifier',

                                'planifie' =>
                                    'Planifié',
                                'partiellement_planifie' =>
                                    'Partiellement planifié',

                                'conflit' =>
                                    'Conflit',

                                'annule' =>
                                    'Annulé',

                                default =>
                                    $state ?? '—',
                            }
                    )
                    ->color(
                        fn (
                            ?string $state
                        ): string =>
                            match ($state) {
                                'a_planifier' =>
                                    'gray',

                                'planifie' =>
                                    'success',
                                'partiellement_planifie' =>
                                    'warning',

                                'conflit' =>
                                    'danger',

                                'annule' =>
                                    'warning',

                                default =>
                                    'gray',
                            }
                    ),


            ])
            ->filters([

                SelectFilter::make(
                    'statut'
                )
                    ->label('Statut')
                    ->options([
                        'a_planifier' =>
                            'À planifier',

                        'partiellement_planifie' =>
                            'Partiellement planifié',

                        'conflit' =>
                            'Conflit',
                    ]),

            ])
            ->toolbarActions([
                \Filament\Actions\BulkAction::make(
                    'planifierSelection'
                )
                    ->label(
                        'Planifier / fusionner'
                    )
                    ->icon(
                        'heroicon-o-calendar-days'
                    )
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Planifier et fusionner les besoins sélectionnés ?'
                    )
                    ->modalDescription(
                        'Les besoins d’un même stage sont regroupés automatiquement lorsque leurs fenêtres de dates le permettent. '
                        . 'Les effectifs prévisionnels sont additionnés et plusieurs sessions sont créées si la capacité du stage l’exige. '
                        . 'Les besoins partiellement planifiés peuvent aussi être complétés.'
                    )
                    ->modalSubmitActionLabel(
                        'Planifier la sélection'
                    )
                    ->action(
                        function (
                            \Illuminate\Database\Eloquent\Collection $records
                        ): void {
                            $result =
                                app(
                                    BesoinFormationGroupedPlanner::class
                                )
                                    ->plan(
                                        $records
                                    );

                            $body =
                                $result['planned']
                                . ' planifié(s) • '
                                . $result['conflicts']
                                . ' conflit(s) • '
                                . $result['skipped']
                                . ' ignoré(s)';

                            if (
                                $result['failed']
                                > 0
                            ) {
                                $body .=
                                    ' • '
                                    . $result['failed']
                                    . ' échec(s)';

                                if (
                                    $result[
                                        'failed_codes'
                                    ]
                                    !== []
                                ) {
                                    $body .=
                                        ' : '
                                        . implode(
                                            ', ',
                                            array_slice(
                                                $result[
                                                    'failed_codes'
                                                ],
                                                0,
                                                5
                                            )
                                        );

                                    if (
                                        count(
                                            $result[
                                                'failed_codes'
                                            ]
                                        )
                                        > 5
                                    ) {
                                        $body .=
                                            '…';
                                    }
                                }
                            }

                            $notification =
                                \Filament\Notifications\Notification::make()
                                    ->title(
                                        'Planification en masse terminée'
                                    )
                                    ->body(
                                        $body
                                    );

                            if (
                                $result['failed']
                                    > 0
                                || $result['conflicts']
                                    > 0
                            ) {
                                $notification
                                    ->warning();
                            } else {
                                $notification
                                    ->success();
                            }

                            $notification
                                ->send();
                        }
                    )
                    ->deselectRecordsAfterCompletion(),
            ])

            ->recordActions([

                Action::make(
                    'planifier'
                )
                    ->label('Planifier')
                    ->icon(
                        'heroicon-o-calendar-days'
                    )
                    ->color('primary')
                    ->visible(
                        fn (
                            BesoinFormation $record
                        ): bool =>
                            (int) $record->effectif_restant > 0
                            && $record->statut !== 'annule'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Planifier ce besoin'
                    )
                    ->modalDescription(
                        'L’application recherche un créneau compatible avec les dates demandées, la durée du stage, les salles, les instructeurs et les autres sessions. '
                        . 'L’effectif demandé ne bloque jamais l’ouverture d’une session : la session conserve la capacité maximale du stage afin que d’autres besoins puissent s’y rattacher ensuite.'
                    )
                    ->schema([
                        /* PLANIFICATION_FORCE_SOUS_MINIMUM_V1 */
                        Toggle::make(
                            'autoriser_sous_minimum'
                        )
                            ->label(
                                'Effectif minimum non bloquant (automatique)'
                            )
                            ->helperText(
                                'Automatique : le nombre demandé ne bloque jamais la création. Les conflits salle/instructeur restent bloquants.'
                            )
                            ->default(
                                true
                            )
                            ->hidden(),
                    ])
                    ->modalSubmitActionLabel(
                        'Rechercher / planifier'
                    )
                    ->action(
                        function (
                            array $data,
                            BesoinFormation $record
                        ): void {
                            $result = app(
                                BesoinFormationGroupedPlanner::class
                            )->plan(
                                $record,
                                (bool) (
                                    $data[
                                        'autoriser_sous_minimum'
                                    ]
                                    ?? false
                                )
                            );

                            if (
                                $result[
                                    'success'
                                ]
                            ) {
                                Notification::make()
                                    ->title(
                                        'Planification effectuée'
                                    )
                                    ->body(
                                        $result[
                                            'message'
                                        ]
                                    )
                                    ->success()
                                    ->send();

                                return;
                            }

                            /*
                             * MOTEUR_GROUPE_CONFLITS_SCALAIRES_V1_1
                             *
                             * Avec BesoinFormationGroupedPlanner :
                             * - conflicts = compteur entier
                             * - conflict_messages = tableau de messages
                             * - message = résumé complet
                             */
                            $body =
                                trim(
                                    (string) (
                                        $result[
                                            'message'
                                        ]
                                        ?? ''
                                    )
                                );

                            if (
                                $body === ''
                            ) {
                                $conflictMessages =
                                    $result[
                                        'conflict_messages'
                                    ]
                                    ?? [];

                                if (
                                    is_array(
                                        $conflictMessages
                                    )
                                    && $conflictMessages !== []
                                ) {
                                    $body =
                                        implode(
                                            "\n\n",
                                            $conflictMessages
                                        );
                                }
                            }

                            if (
                                ! empty(
                                    $result[
                                        'suggestions'
                                    ]
                                )
                            ) {
                                $body .=
                                    "\n\nSolutions possibles :\n"
                                    . $result[
                                        'suggestions'
                                    ];
                            }

                            Notification::make()
                                ->title(
                                    'Planification impossible'
                                )
                                ->body(
                                    $body
                                    ?: 'Aucun créneau compatible n’a été trouvé.'
                                )
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    ),

                ViewAction::make(),

                EditAction::make(),
            ])
            ->defaultSort(
                'date_debut_souhaitee',
                'asc'
            );
    }
}
