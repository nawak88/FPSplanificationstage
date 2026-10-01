<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Inscription extends SubmissionPage
{
    protected string $view = 'fpsplanificationstage::filament.public.inscription';
    protected static string|array $routeMiddleware = [\Modules\FPSplanificationstage\Http\Middleware\RequireMindefConnectAuthentication::class];

    #[\Livewire\Attributes\Locked]
    public int $sessionId;

    public function mount(int|string $session): void
    {
        abort_unless(auth()->user(), 403);
        $this->sessionId = (int) $session;
        $this->form->fill($this->formData()['identity']);
    }

    private function formData(): array
    {
        abort_unless(auth()->user(), 403);
        return app(\Modules\FPSplanificationstage\Services\PublicInscriptionPageService::class)->form(
            \Modules\FPSplanificationstage\Models\SessionStage::query()->findOrFail($this->sessionId), auth()->user()
        );
    }

    protected function getViewData(): array
    {
        return $this->formData();
    }

    public function form(Schema $schema): Schema
    {
        $formData = $this->formData();
        $prerequis = [];
        foreach ($formData['session']->stage->prerequis as $record) {
            $prerequis[] = Checkbox::make('prerequis.' . $record->id)->label($record->libelle . ($record->obligatoire ? ' — obligatoire' : ''));
        }
        return $schema->statePath('data')->components([
            Section::make('Vos informations')->description('Votre nom, votre prénom et votre adresse électronique proviennent de votre connexion MindefConnect.')->schema([
                TextInput::make('nom')->label('Nom')->readOnly(),
                TextInput::make('prenom')->label('Prénom')->readOnly(),
                TextInput::make('email')->label('E-mail')->readOnly(),
                TextInput::make('matricule')->label('Matricule')->maxLength(100),
                TextInput::make('nid')->label('NID')->maxLength(100),
                TextInput::make('unite')->label('Bâtiment / unité')->required()->maxLength(255),
                Select::make('grade')->label('Grade')->searchable()->options($formData['grades']->mapWithKeys(fn ($record) => [$record->libelle_court => ($record->libelle_long ?: $record->libelle_court) . ' — ' . $record->libelle_court])->all()),
                Select::make('specialite')->label('Spécialité')->searchable()->options($formData['specialites']->mapWithKeys(fn ($record) => [$record->libelle_court => ($record->libelle_long ?: $record->libelle_court) . ' — ' . $record->libelle_court])->all()),
                Select::make('brevet')->label('Brevet')->searchable()->options($formData['brevets']->mapWithKeys(fn ($record) => [$record->libelle_court => ($record->libelle_long ?: $record->libelle_court) . ' — ' . $record->libelle_court])->all()),
                Select::make('motif_inscription')->label('Motif')->required()->options($formData['motifOptions']),
            ])->columns(2),
            Section::make('Pré-requis')->schema([
                ...$prerequis,
                Checkbox::make('demande_derogation')->label("Demander une dérogation si un pré-requis obligatoire n'est pas rempli"),
                Textarea::make('derogation_motif')->label('Motif de la dérogation')->maxLength(3000),
            ]),
        ]);
    }

    public function submit(): void
    {
        abort_unless(auth()->user(), 403);
        $this->submitForm(fn (array $data) => app(\Modules\FPSplanificationstage\Services\PublicInscriptionSubmissionService::class)->store(
            $data, \Modules\FPSplanificationstage\Models\SessionStage::query()->findOrFail($this->sessionId)
        ), 20);
    }
}
