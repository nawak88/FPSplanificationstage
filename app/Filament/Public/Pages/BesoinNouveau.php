<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BesoinNouveau extends SubmissionPage
{
    protected string $view = 'fpsplanificationstage::filament.public.besoin-formation';

    public function mount(): void
    {
        $defaults = app(\Modules\FPSplanificationstage\Services\PublicBesoinFormationPageService::class)->form(auth()->user());
        $this->form->fill(['demandeur' => $defaults['demandeur']]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Informations du demandeur')->schema([
                Select::make('demandeur')->label('Bâtiment / unité')->searchable()->required()
                    ->options(fn () => \Modules\RH\Models\Unite::query()->whereNotNull('libelle_long')->where('libelle_long', '<>', '')->orderBy('libelle_long')->pluck('libelle_long', 'libelle_long')->all()),
                TextInput::make('contact_nom')->label('Nom du contact')->required()->maxLength(255),
                TextInput::make('contact_email')->label('E-mail')->email()->required()->maxLength(255),
                TextInput::make('contact_telephone')->label('Téléphone')->maxLength(255),
                Select::make('type_periode')->label('Période du demandeur')->required()->live()->default('plage')
                    ->options([
                        'plage' => 'Période de disponibilité',
                        'indisponibilite' => 'Période d’indisponibilité',
                    ])
                    ->helperText(fn (Get $get) => $get('type_periode') === 'indisponibilite'
                        ? 'Tous les stages ajoutés seront planifiés avant ou après cette période. La recherche commence aujourd’hui et s’étend jusqu’à un an après la fin de l’indisponibilité.'
                        : 'Tous les stages ajoutés doivent être entièrement réalisés dans cette période.'),
                DatePicker::make('date_debut_souhaitee')
                    ->label(fn (Get $get) => $get('type_periode') === 'indisponibilite' ? 'Indisponible du' : 'Disponible du')
                    ->required()->live()->minDate(today()),
                DatePicker::make('date_fin_souhaitee')->label('Au')->required()
                    ->minDate(fn (Get $get) => $get('date_debut_souhaitee') ?: today())
                    ->afterOrEqual('date_debut_souhaitee'),
            ])->columns(2),
            Repeater::make('besoins')->label('Besoins de formation')->minItems(1)->maxItems(20)->defaultItems(1)
                ->addActionLabel('Ajouter un stage')->schema([
                    Select::make('stage_id')->label('Stage')->required()->searchable()->live()
                        ->options(fn () => \Modules\FPSplanificationstage\Models\Stage::query()->where('actif', true)->orderBy('libelle_court')->pluck('libelle_court', 'id')->all()),
                    TextInput::make('nombre_stagiaires')->label('Nombre de stagiaires')->numeric()->integer()->minValue(1)->maxValue(999)->default(1)->required(),
                    Textarea::make('commentaire')->label('Commentaire')->maxLength(5000),
                ])->columns(2),
        ]);
    }

    public function submit(): void
    {
        $this->submitForm(fn (array $data) => app(\Modules\FPSplanificationstage\Services\PublicBesoinFormationSubmissionService::class)->store($data), 20);
    }
}
