<?php

namespace Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\FPSplanificationstage\Models\Stage;

class BesoinFormationForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([

                Section::make(
                    'Demande de formation'
                )
                    ->columns(2)
                    ->schema([

                        TextInput::make(
                            'code_besoin'
                        )
                            ->label(
                                'Code du besoin'
                            )
                            ->disabled()
                            ->placeholder(
                                'Généré automatiquement'
                            ),

                        Select::make(
                            'statut'
                        )
                            ->label('Statut')
                            ->options([
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
                            ])
                            ->default(
                                'a_planifier'
                            )
                            ->required(),

                        TextInput::make(
                            'demandeur'
                        )
                            ->label(
                                'Bâtiment / unité demandeur'
                            )
                            ->placeholder(
                                'Ex. FDA FORBIN'
                            )
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make(
                            'stage_id'
                        )
                            ->label(
                                'Stage demandé'
                            )
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
                            ->columnSpanFull()
                            ->live(),
                    ]),

                Section::make(
                    'Contact demandeur'
                )
                    ->description(
                        'Coordonnées de la personne à contacter pour cette expression de besoin.'
                    )
                    ->columns(2)
                    ->schema([

                        TextInput::make(
                            'contact_nom'
                        )
                            ->label(
                                'Nom du contact'
                            )
                            ->maxLength(255),

                        TextInput::make(
                            'contact_email'
                        )
                            ->label(
                                'E-mail du contact'
                            )
                            ->email()
                            ->maxLength(255),

                        TextInput::make(
                            'contact_telephone'
                        )
                            ->label(
                                'Téléphone du contact'
                            )
                            ->maxLength(255),

                        TextInput::make(
                            'source'
                        )
                            ->label('Source')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(
                                'manuel'
                            ),
                    ]),

                Section::make(
                    'Période souhaitée'
                )
                    ->description(
                        'Date de début imposée : une seule date, la fin est calculée automatiquement selon la durée du stage. '
                        . 'Plage de disponibilité : le stage complet doit tenir dans la fenêtre, qui doit être au moins aussi longue que le stage en jours ouvrés. '
                        . 'Plage de démarrage : le stage peut commencer n’importe quel jour de la fenêtre et se terminer après celle-ci.'
                    )
                    ->columns(2)
                    ->schema([

                        Select::make(
                            'type_periode'
                        )
                            ->label('Type de demande')
                            ->options([
                                'dates_fixes' =>
                                    'Date de début imposée',

                                'plage' =>
                                    'Période disponible',

                                'plage_demarrage' =>
                                    'Période de démarrage',
                                'indisponibilite' => 'Période d’indisponibilité',
                            ])
                            ->default('dates_fixes')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        DatePicker::make(
                            'date_debut_souhaitee'
                        )
                            ->label('Date de début / début de plage')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->live(),

                        DatePicker::make(
                            'date_fin_souhaitee'
                        )
                            ->label('Fin de la plage')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->visible(
                                fn ($get): bool =>
                                    $get('type_periode') !== 'dates_fixes'
                            )
                            ->required(
                                fn ($get): bool =>
                                    in_array(
                                        $get('type_periode'),
                                        [
                                            'plage',
                                            'plage_demarrage',
                                            'indisponibilite',
                                        ],
                                        true
                                    )
                            )
                            ->minDate(
                                function ($get): ?string {
                                    $start = $get('date_debut_souhaitee');

                                    if (! $start) {
                                        return null;
                                    }

                                    if ($get('type_periode') === 'plage') {
                                        return \Modules\FPSplanificationstage\Services\BesoinPeriodeService::minimumEndDate(
                                            $get('stage_id'),
                                            $start
                                        )?->format('Y-m-d');
                                    }

                                    return \Carbon\Carbon::parse($start)->format('Y-m-d');
                                }
                            )
                            ->rules(
                                fn ($get): array =>
                                    $get('type_periode') === 'dates_fixes'
                                        ? []
                                        : [
                                            'after_or_equal:date_debut_souhaitee',
                                        ]
                            )
                            ->helperText(
                                fn ($get): string =>
                                    \Modules\FPSplanificationstage\Services\BesoinPeriodeService::helperText(
                                        $get('stage_id'),
                                        $get('type_periode')
                                    )
                            )
                            ->dehydrated(
                                fn ($get): bool =>
                                    $get('type_periode') !== 'dates_fixes'
                            ),
                    ]),

                Section::make(
                    'Priorité et besoin estimé'
                )
                    ->columns(2)
                    ->schema([

                        TextInput::make(
                            'nombre_stagiaires'
                        )
                            ->label(
                                'Besoin estimé de personnels'
                            )
                            ->numeric()
                            ->minValue(1)
                            ->suffix(
                                'personnes'
                            )
                            ->helperText(
                                'Ce nombre est indicatif : il ne réserve aucune place dans la session.'
                            ),
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
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
