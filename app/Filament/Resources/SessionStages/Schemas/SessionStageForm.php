<?php

namespace Modules\FPSplanificationstage\Filament\Resources\SessionStages\Schemas;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\SessionStageAlternativeFinder;
use Modules\FPSplanificationstage\Services\SessionStageConflictDetector;
use App\Models\User;

class SessionStageForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([

                /*
                 * États temporaires.
                 *
                 * Ils servent uniquement à afficher les
                 * propositions et ne sont jamais enregistrés
                 * dans la base.
                 */
                Hidden::make('suggestions_salles')
                    ->default([])
                    ->dehydrated(false),

                Hidden::make('suggestions_dates')
                    ->default([])
                    ->dehydrated(false),

                Section::make('Session')
                    ->columns(2)
                    ->schema([

                        TextInput::make('code_session')
                            ->label('Code session')
                            ->disabled()
                            ->placeholder(
                                'Généré automatiquement'
                            ),

                        Select::make('statut')
                            ->label('Statut')
                            ->options([
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
                            ])
                            ->default('brouillon')
                            ->required(),

                        Select::make('stage_id')
                            ->label('Stage')
                            ->relationship(
                                name: 'stage',
                                titleAttribute:
                                    'libelle_court',

                                modifyQueryUsing:
                                    fn ($query) =>
                                        $query->where(
                                            'actif',
                                            true
                                        )
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (
                                    Stage $record
                                ): string =>
                                    (
                                        $record->code_stage
                                            ? $record
                                                ->code_stage
                                                . ' — '
                                            : ''
                                    )
                                    . $record
                                        ->libelle_court
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    Set $set,
                                    $state
                                ): void {
                                    $set(
                                        'suggestions_salles',
                                        []
                                    );

                                    $set(
                                        'suggestions_dates',
                                        []
                                    );

                                    if (! $state) {
                                        $set(
                                            'capacite_min',
                                            null
                                        );

                                        $set(
                                            'capacite_max',
                                            null
                                        );

                                        $set(
                                            'instructeurs',
                                            []
                                        );

                                        $set(
                                            'salle_id',
                                            null
                                        );

                                        $set(
                                            'salle_forcee',
                                            false
                                        );

                                        return;
                                    }

                                    $stage =
                                        Stage::query()
                                            ->find(
                                                $state
                                            );

                                    if (! $stage) {
                                        return;
                                    }

                                    $set(
                                        'capacite_min',
                                        $stage
                                            ->capacite_min
                                    );

                                    $set(
                                        'capacite_max',
                                        $stage
                                            ->capacite_max
                                    );

                                    $instructeurIds =
                                        $stage
                                            ->instructeurs()
                                            ->wherePivot(
                                                'actif',
                                                true
                                            )
                                            ->pluck(
                                                'users.id'
                                            )
                                            ->all();

                                    $set(
                                        'instructeurs',
                                        $instructeurIds
                                    );

                                    $salleId = null;

                                    if (
                                        $stage
                                            ->salle_preferentielle_id
                                    ) {
                                        $salleActive =
                                            Salle::query()
                                                ->whereKey(
                                                    $stage
                                                        ->salle_preferentielle_id
                                                )
                                                ->where(
                                                    'actif',
                                                    true
                                                )
                                                ->exists();

                                        if (
                                            $salleActive
                                        ) {
                                            $salleId =
                                                $stage
                                                    ->salle_preferentielle_id;
                                        }
                                    }

                                    $set(
                                        'salle_id',
                                        $salleId
                                    );

                                    $set(
                                        'salle_forcee',
                                        false
                                    );
                                }
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make(
                    'Dates et horaires'
                )
                    ->description(
                        'Les stages commencent normalement à 08:00 et se terminent à 16:00.'
                    )
                    ->columns(2)
                    ->schema([

                        DatePicker::make(
                            'debut_date'
                        )
                            ->label(
                                'Date de début'
                            )
                            ->native(false)
                            ->displayFormat(
                                'd/m/Y'
                            )
                            ->required(),

                        Select::make(
                            'debut_heure'
                        )
                            ->label(
                                'Heure de début'
                            )
                            ->options([
                                '08:00' => '08:00',
                                '09:00' => '09:00',
                                '10:00' => '10:00',
                                '11:00' => '11:00',
                                '12:00' => '12:00',
                                '13:00' => '13:00',
                                '14:00' => '14:00',
                                '15:00' => '15:00',
                            ])
                            ->default('08:00')
                            ->required()
                            ->native(false),

                        DatePicker::make(
                            'fin_date'
                        )
                            ->label(
                                'Date de fin'
                            )
                            ->native(false)
                            ->displayFormat(
                                'd/m/Y'
                            )
                            ->required(),

                        Select::make(
                            'fin_heure'
                        )
                            ->label(
                                'Heure de fin'
                            )
                            ->options([
                                '09:00' => '09:00',
                                '10:00' => '10:00',
                                '11:00' => '11:00',
                                '12:00' => '12:00',
                                '13:00' => '13:00',
                                '14:00' => '14:00',
                                '15:00' => '15:00',
                                '16:00' => '16:00',
                            ])
                            ->default('16:00')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Instructeurs')
                    ->description(
                        'Les instructeurs habilités pour le stage sont ajoutés automatiquement. Vous pouvez modifier la sélection.'
                    )
                    ->schema([

                        Select::make(
                            'instructeurs'
                        )
                            ->label(
                                'Instructeurs'
                            )
                            ->relationship(
                                name:
                                    'instructeurs',

                                titleAttribute:
                                    'nom',

                                modifyQueryUsing:
                                    fn ($query) => $query
                            )
                            ->multiple()
                            ->getOptionLabelFromRecordUsing(
                                fn (
                                    User $record
                                ): string =>
                                    trim(
                                        mb_strtoupper(
                                            $record->nom
                                        )
                                        . ' '
                                        . $record
                                            ->prenom
                                    )
                                    . (
                                        $record
                                            ->matricule
                                            ? ' — '
                                                . $record
                                                    ->matricule
                                            : ''
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),

                Section::make('Salle')
                    ->columns(2)
                    ->schema([

                        Select::make('salle_id')
                            ->label(
                                'Salle affectée'
                            )
                            ->relationship(
                                name: 'salle',

                                titleAttribute:
                                    'nom',

                                modifyQueryUsing:
                                    fn ($query) =>
                                        $query->where(
                                            'actif',
                                            true
                                        )
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (
                                    Salle $record
                                ): string =>
                                    (
                                        $record->code
                                            ? $record
                                                ->code
                                                . ' — '
                                            : ''
                                    )
                                    . $record->nom
                                    . (
                                        $record->capacite
                                            ? ' ('
                                                . $record
                                                    ->capacite
                                                . ' pers.)'
                                            : ''
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder(
                                'Aucune salle affectée'
                            )
                            ->helperText(
                                'La salle préférentielle du stage est proposée automatiquement, mais reste modifiable.'
                            ),

                        Toggle::make(
                            'salle_forcee'
                        )
                            ->label(
                                'Salle imposée manuellement'
                            )
                            ->default(false)
                            ->helperText(
                                'À activer si vous choisissez volontairement une autre salle que celle proposée.'
                            ),
                    ]),

                Section::make('Capacité')
                    ->columns(2)
                    ->schema([

                        TextInput::make(
                            'capacite_min'
                        )
                            ->label(
                                'Capacité minimale'
                            )
                            ->numeric()
                            ->minValue(0)
                            ->helperText(
                                'Reprise du catalogue, mais modifiable pour cette session.'
                            ),

                        TextInput::make(
                            'capacite_max'
                        )
                            ->label(
                                'Capacité maximale'
                            )
                            ->numeric()
                            ->minValue(1)
                            ->helperText(
                                'Reprise du catalogue, mais modifiable pour cette session.'
                            ),
                    ]),

                /*
                 * Nouveau bloc :
                 * vérification et solutions cliquables.
                 */
                Section::make(
                    'Vérification du planning'
                )
                    ->description(
                        'Vérifiez les disponibilités avant de créer la session. En cas de conflit, vous pourrez appliquer directement une solution proposée.'
                    )
                    ->schema([

                        Actions::make([
                            Action::make(
                                'chercherAlternatives'
                            )
                                ->label(
                                    'Vérifier les disponibilités'
                                )
                                ->icon(
                                    'heroicon-o-magnifying-glass'
                                )
                                ->color('gray')
                                ->button()
                                ->action(
                                    function (
                                        Get $schemaGet,
                                        Set $schemaSet,
                                        ?SessionStage $record
                                    ): void {
                                        $data =
                                            self::buildConflictData(
                                                $schemaGet
                                            );

                                        if (
                                            $data === null
                                        ) {
                                            Notification::make()
                                                ->title(
                                                    'Informations incomplètes'
                                                )
                                                ->body(
                                                    'Renseignez le stage, les dates et les heures avant de vérifier le planning.'
                                                )
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $sessionId =
                                            $record
                                                ? (int) $record
                                                    ->getKey()
                                                : null;

                                        $conflits =
                                            app(
                                                SessionStageConflictDetector::class
                                            )->detect(
                                                $data,
                                                $sessionId
                                            );

                                        if (
                                            $conflits === []
                                        ) {
                                            $schemaSet(
                                                'suggestions_salles',
                                                []
                                            );

                                            $schemaSet(
                                                'suggestions_dates',
                                                []
                                            );

                                            Notification::make()
                                                ->title(
                                                    'Planning disponible'
                                                )
                                                ->body(
                                                    'Aucun conflit détecté pour cette session.'
                                                )
                                                ->success()
                                                ->send();

                                            return;
                                        }

                                        $suggestions =
                                            app(
                                                SessionStageAlternativeFinder::class
                                            )->find(
                                                $data,
                                                $sessionId
                                            );

                                        $schemaSet(
                                            'suggestions_salles',
                                            $suggestions[
                                                'salles'
                                            ]
                                        );

                                        $schemaSet(
                                            'suggestions_dates',
                                            $suggestions[
                                                'dates'
                                            ]
                                        );

                                        Notification::make()
                                            ->title(
                                                'Conflit détecté'
                                            )
                                            ->body(
                                                implode(
                                                    "\n",
                                                    $conflits
                                                )
                                                . "\n\nLes solutions disponibles sont affichées ci-dessous."
                                            )
                                            ->warning()
                                            ->send();
                                    }
                                ),
                        ])
                            ->fullWidth(),

                        Actions::make([
                            self::makeSalleAction(
                                0
                            ),

                            self::makeSalleAction(
                                1
                            ),

                            self::makeSalleAction(
                                2
                            ),
                        ])
                            ->visible(
                                fn (
                                    Get $schemaGet
                                ): bool =>
                                    ! empty(
                                        $schemaGet(
                                            'suggestions_salles'
                                        )
                                    )
                            )
                            ->fullWidth(),

                        Actions::make([
                            self::makeDateAction(
                                0
                            ),

                            self::makeDateAction(
                                1
                            ),

                            self::makeDateAction(
                                2
                            ),
                        ])
                            ->visible(
                                fn (
                                    Get $schemaGet
                                ): bool =>
                                    ! empty(
                                        $schemaGet(
                                            'suggestions_dates'
                                        )
                                    )
                            )
                            ->fullWidth(),
                    ]),

                Section::make(
                    'Informations complémentaires'
                )
                    ->schema([

                        Textarea::make(
                            'commentaire'
                        )
                            ->label(
                                'Commentaire'
                            )
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function makeSalleAction(
        int $index
    ): Action {
        return Action::make(
            'utiliserSalle' . $index
        )
            ->label(
                function (
                    Get $schemaGet
                ) use ($index): string {
                    $salles =
                        $schemaGet(
                            'suggestions_salles'
                        ) ?? [];

                    if (
                        ! isset(
                            $salles[$index]
                        )
                    ) {
                        return 'Salle';
                    }

                    return
                        'Utiliser '
                        . $salles[
                            $index
                        ]['label'];
                }
            )
            ->icon(
                'heroicon-o-building-office'
            )
            ->color('success')
            ->outlined()
            ->visible(
                function (
                    Get $schemaGet
                ) use ($index): bool {
                    $salles =
                        $schemaGet(
                            'suggestions_salles'
                        ) ?? [];

                    return isset(
                        $salles[$index]['id']
                    );
                }
            )
            ->action(
                function (
                    Get $schemaGet,
                    Set $schemaSet
                ) use ($index): void {
                    $salles =
                        $schemaGet(
                            'suggestions_salles'
                        ) ?? [];

                    if (
                        ! isset(
                            $salles[$index]['id']
                        )
                    ) {
                        return;
                    }

                    $schemaSet(
                        'salle_id',
                        $salles[
                            $index
                        ]['id']
                    );

                    /*
                     * Le choix est différent de
                     * la préférence du stage.
                     */
                    $schemaSet(
                        'salle_forcee',
                        true
                    );

                    $schemaSet(
                        'suggestions_salles',
                        []
                    );

                    $schemaSet(
                        'suggestions_dates',
                        []
                    );

                    Notification::make()
                        ->title(
                            'Salle appliquée'
                        )
                        ->body(
                            $salles[
                                $index
                            ]['label']
                        )
                        ->success()
                        ->send();
                }
            );
    }

    private static function makeDateAction(
        int $index
    ): Action {
        return Action::make(
            'utiliserDate' . $index
        )
            ->label(
                function (
                    Get $schemaGet
                ) use ($index): string {
                    $dates =
                        $schemaGet(
                            'suggestions_dates'
                        ) ?? [];

                    if (
                        ! isset(
                            $dates[$index]
                        )
                    ) {
                        return 'Créneau';
                    }

                    return
                        'Utiliser '
                        . $dates[
                            $index
                        ]['label'];
                }
            )
            ->icon(
                'heroicon-o-calendar-days'
            )
            ->color('success')
            ->outlined()
            ->visible(
                function (
                    Get $schemaGet
                ) use ($index): bool {
                    $dates =
                        $schemaGet(
                            'suggestions_dates'
                        ) ?? [];

                    return isset(
                        $dates[$index][
                            'debut'
                        ],
                        $dates[$index][
                            'fin'
                        ]
                    );
                }
            )
            ->action(
                function (
                    Get $schemaGet,
                    Set $schemaSet
                ) use ($index): void {
                    $dates =
                        $schemaGet(
                            'suggestions_dates'
                        ) ?? [];

                    if (
                        ! isset(
                            $dates[$index][
                                'debut'
                            ],
                            $dates[$index][
                                'fin'
                            ]
                        )
                    ) {
                        return;
                    }

                    $debut = Carbon::parse(
                        $dates[$index][
                            'debut'
                        ]
                    );

                    $fin = Carbon::parse(
                        $dates[$index][
                            'fin'
                        ]
                    );

                    $schemaSet(
                        'debut_date',
                        $debut->format(
                            'Y-m-d'
                        )
                    );

                    $schemaSet(
                        'debut_heure',
                        $debut->format(
                            'H:i'
                        )
                    );

                    $schemaSet(
                        'fin_date',
                        $fin->format(
                            'Y-m-d'
                        )
                    );

                    $schemaSet(
                        'fin_heure',
                        $fin->format(
                            'H:i'
                        )
                    );

                    $schemaSet(
                        'suggestions_salles',
                        []
                    );

                    $schemaSet(
                        'suggestions_dates',
                        []
                    );

                    Notification::make()
                        ->title(
                            'Créneau appliqué'
                        )
                        ->body(
                            $dates[
                                $index
                            ]['label']
                        )
                        ->success()
                        ->send();
                }
            );
    }

    private static function buildConflictData(
        Get $schemaGet
    ): ?array {
        $debutDate =
            $schemaGet(
                'debut_date'
            );

        $debutHeure =
            $schemaGet(
                'debut_heure'
            );

        $finDate =
            $schemaGet(
                'fin_date'
            );

        $finHeure =
            $schemaGet(
                'fin_heure'
            );

        if (
            empty($debutDate)
            || empty($debutHeure)
            || empty($finDate)
            || empty($finHeure)
        ) {
            return null;
        }

        try {
            $dateDebut =
                Carbon::parse(
                    $debutDate
                )->format(
                    'Y-m-d'
                );

            $dateFin =
                Carbon::parse(
                    $finDate
                )->format(
                    'Y-m-d'
                );

            $debut =
                Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $dateDebut
                    . ' '
                    . $debutHeure
                );

            $fin =
                Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $dateFin
                    . ' '
                    . $finHeure
                );
        } catch (\Throwable) {
            return null;
        }

        return [
            'stage_id' =>
                $schemaGet(
                    'stage_id'
                ),

            'debut' =>
                $debut->format(
                    'Y-m-d H:i:s'
                ),

            'fin' =>
                $fin->format(
                    'Y-m-d H:i:s'
                ),

            'salle_id' =>
                $schemaGet(
                    'salle_id'
                ),

            'capacite_min' =>
                $schemaGet(
                    'capacite_min'
                ),

            'capacite_max' =>
                $schemaGet(
                    'capacite_max'
                ),

            'instructeurs' =>
                $schemaGet(
                    'instructeurs'
                ) ?? [],
        ];
    }
}
