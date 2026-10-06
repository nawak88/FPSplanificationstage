<?php

namespace Modules\FPSplanificationstage\Filament\Resources\SessionStages\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\SessionStageLifecycleService;

class SessionStagesTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->columns([

                TextColumn::make(
                    'code_session'
                )
                    ->label('Session')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make(
                    'stage.libelle_court'
                )
                    ->label('Stage')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make(
                    'debut'
                )
                    ->label('Début')
                    ->dateTime(
                        'd/m/Y H:i'
                    )
                    ->sortable(),

                TextColumn::make(
                    'fin'
                )
                    ->label('Fin')
                    ->dateTime(
                        'd/m/Y H:i'
                    )
                    ->sortable(),

                TextColumn::make(
                    'salle.nom'
                )
                    ->label('Salle')
                    ->placeholder(
                        'Non affectée'
                    ),

                TextColumn::make(
                    'instructeurs.nom'
                )
                    ->label(
                        'Formateurs'
                    )
                    ->listWithLineBreaks()
                    ->limitList(3),

                TextColumn::make(
                    'places_reservees'
                )
                    ->label('Places')
                    ->badge()
                    ->formatStateUsing(
                        function (
                            mixed $state,
                            SessionStage $record
                        ): string {
                            $reservees =
                                $record
                                    ->places_reservees;

                            if (
                                $record
                                    ->capacite_max
                                === null
                            ) {
                                return $reservees
                                    . ' / ∞';
                            }

                            return $reservees
                                . ' / '
                                . $record
                                    ->capacite_max;
                        }
                    )
                    ->color(
                        function (
                            SessionStage $record
                        ): string {
                            $capacite =
                                $record
                                    ->capacite_max;

                            if (
                                $capacite === null
                            ) {
                                return 'gray';
                            }

                            if (
                                $capacite <= 0
                            ) {
                                return 'danger';
                            }

                            $reservees =
                                $record
                                    ->places_reservees;

                            if (
                                $reservees
                                >= $capacite
                            ) {
                                return 'danger';
                            }

                            $taux =
                                $reservees
                                / $capacite;

                            if (
                                $taux >= 0.8
                            ) {
                                return 'warning';
                            }

                            return 'success';
                        }
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
                                'brouillon' =>
                                    'Brouillon',

                                'planifiee' =>
                                    'Planifiée',

                                'confirmee' =>
                                    'Confirmée',

                                'annulee' =>
                                    'Annulée',

                                'terminee' =>
                                    'Terminée',

                                default =>
                                    $state ?? '—',
                            }
                    )
                    ->color(
                        fn (
                            ?string $state
                        ): string =>
                            match ($state) {
                                'brouillon' =>
                                    'gray',

                                'planifiee' =>
                                    'info',

                                'confirmee' =>
                                    'success',

                                'annulee' =>
                                    'danger',

                                'terminee' =>
                                    'gray',

                                default =>
                                    'gray',
                            }
                    ),
            ])
            ->recordActions([

                ViewAction::make(),

                Action::make(
                    'deplacerSession'
                )
                    ->label(
                        'Déplacer'
                    )
                    ->icon(
                        'heroicon-o-arrow-path'
                    )
                    ->color(
                        'warning'
                    )
                    ->visible(
                        fn (
                            SessionStage $record
                        ): bool =>
                            in_array(
                                $record->statut,
                                [
                                    'planifiee',
                                    'confirmee',
                                ],
                                true
                            )
                    )
                    ->modalHeading(
                        'Déplacer la session'
                    )
                    ->modalDescription(
                        'La session conserve son numéro, ses stagiaires, sa salle et ses formateurs. '
                        . 'La durée et les horaires sont conservés. '
                        . 'Les samedis et dimanches sont ignorés. '
                        . 'Tous les conflits seront contrôlés avant validation.'
                    )
                    ->modalSubmitActionLabel(
                        'Vérifier et déplacer'
                    )
                    ->schema([

                        DatePicker::make(
                            'nouvelle_date'
                        )
                            ->label(
                                'Nouvelle date de début'
                            )
                            ->native(false)
                            ->required(),

                        Select::make(
                            'motif'
                        )
                            ->label(
                                'Motif du déplacement'
                            )
                            ->options([
                                'instructeur_indisponible' =>
                                    'Formateur indisponible',

                                'salle_indisponible' =>
                                    'Salle indisponible',

                                'contrainte_operationnelle' =>
                                    'Contrainte opérationnelle / service',

                                'calendrier' =>
                                    'Modification du calendrier',

                                'autre' =>
                                    'Autre',
                            ])
                            ->required(),

                        Textarea::make(
                            'commentaire'
                        )
                            ->label(
                                'Précisions'
                            )
                            ->rows(3)
                            ->maxLength(
                                2000
                            ),
                    ])
                    ->action(
                        function (
                            array $data,
                            SessionStage $record
                        ): void {
                            $result =
                                app(
                                    SessionStageLifecycleService::class
                                )
                                    ->deplacer(
                                        $record,
                                        (string)
                                            $data[
                                                'nouvelle_date'
                                            ],
                                        (string)
                                            $data[
                                                'motif'
                                            ],
                                        $data[
                                            'commentaire'
                                        ] ?? null
                                    );

                            if (
                                $result[
                                    'success'
                                ]
                                ?? false
                            ) {
                                Notification::make()
                                    ->title(
                                        'Session déplacée'
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

                            Notification::make()
                                ->title(
                                    'Déplacement impossible'
                                )
                                ->body(
                                    implode(
                                        "\n\n",
                                        $result[
                                            'conflicts'
                                        ] ?? [
                                            'Le déplacement n’a pas pu être effectué.',
                                        ]
                                    )
                                )
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    ),

                Action::make(
                    'annulerSession'
                )
                    ->label(
                        'Annuler'
                    )
                    ->icon(
                        'heroicon-o-x-circle'
                    )
                    ->color(
                        'danger'
                    )
                    ->visible(
                        fn (
                            SessionStage $record
                        ): bool =>
                            ! in_array(
                                $record->statut,
                                [
                                    'annulee',
                                    'terminee',
                                ],
                                true
                            )
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Annuler définitivement cette session ?'
                    )
                    ->modalDescription(
                        'Cette action ne replanifie pas le besoin. '
                        . 'La session restera dans l’historique avec le statut « Annulée » '
                        . 'et les candidatures encore actives seront annulées.'
                    )
                    ->modalSubmitActionLabel(
                        'Annuler définitivement'
                    )
                    ->schema([

                        Select::make(
                            'motif'
                        )
                            ->label(
                                'Motif de l’annulation'
                            )
                            ->options([
                                'aucun_candidat' =>
                                    'Aucun candidat inscrit',

                                'effectif_insuffisant' =>
                                    'Effectif insuffisant',

                                'decision_service' =>
                                    'Décision du service',

                                'instructeur_indisponible' =>
                                    'Formateur indisponible',

                                'autre' =>
                                    'Autre',
                            ])
                            ->required(),

                        Textarea::make(
                            'commentaire'
                        )
                            ->label(
                                'Précisions'
                            )
                            ->rows(3)
                            ->maxLength(
                                2000
                            ),
                    ])
                    ->action(
                        function (
                            array $data,
                            SessionStage $record
                        ): void {
                            $result =
                                app(
                                    SessionStageLifecycleService::class
                                )
                                    ->annuler(
                                        $record,
                                        (string)
                                            $data[
                                                'motif'
                                            ],
                                        $data[
                                            'commentaire'
                                        ] ?? null
                                    );

                            if (
                                ! (
                                    $result[
                                        'success'
                                    ]
                                    ?? false
                                )
                            ) {
                                Notification::make()
                                    ->title(
                                        'Annulation impossible'
                                    )
                                    ->body(
                                        $result[
                                            'message'
                                        ]
                                        ?? 'La session n’a pas été annulée.'
                                    )
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(
                                    'Session annulée'
                                )
                                ->body(
                                    $result[
                                        'message'
                                    ]
                                    . ' '
                                    . (
                                        $result[
                                            'inscriptions_annulees'
                                        ]
                                        ?? 0
                                    )
                                    . ' candidature(s) active(s) annulée(s).'
                                )
                                ->success()
                                ->send();
                        }
                    ),

                EditAction::make(),
            ])
            ->defaultSort(
                'debut',
                'asc'
            );
    }
}
