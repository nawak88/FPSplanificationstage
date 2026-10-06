<?php

namespace Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IndisponibiliteInstructeurForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Formateur')
                    ->schema([
                        Select::make('instructeur_id')
                            ->label('Formateur')
                            ->relationship(
                                name: 'instructeur',
                                titleAttribute: 'nom'
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn ($record): string =>
                                    trim(
                                        mb_strtoupper($record->nom)
                                        . ' '
                                        . $record->prenom
                                    )
                            )
                            ->searchable([
                                'nom',
                                'prenom',
                                'matricule',
                                'nid',
                            ])
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Période d’indisponibilité')
                    ->description(
                        'Saisissez une journée entière, plusieurs jours ou seulement un créneau horaire.'
                    )
                    ->columns(2)
                    ->schema([

                        Toggle::make('journee_entiere')
                            ->label('Journée entière')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

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
                    ]),

                Section::make('Informations complémentaires')
                    ->columns(2)
                    ->schema([

                        Select::make('motif')
                            ->label('Motif')
                            ->options([
                                'conge' => 'Congé',
                                'mission' => 'Mission',
                                'formation' => 'Formation',
                                'service' => 'Service',
                                'absence' => 'Absence',
                                'autre' => 'Autre',
                            ])
                            ->searchable(),

                        Toggle::make('actif')
                            ->label('Indisponibilité active')
                            ->default(true),

                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
