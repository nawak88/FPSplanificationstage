<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BesoinSuiviRecherche extends SubmissionPage
{
    protected string $view = 'fpsplanificationstage::filament.public.besoin-formation-suivi-recherche';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('code_besoin')->label('Référence du besoin')->placeholder('BES-000001')->required()->maxLength(255),
            TextInput::make('contact_email')->label('E-mail')->email()->required()->maxLength(255),
        ]);
    }

    public function submit(): void
    {
        $this->submitForm(fn (array $data) => app(\Modules\FPSplanificationstage\Services\PublicBesoinFormationSubmissionService::class)->rechercherSuivi($data), 10);
    }
}
