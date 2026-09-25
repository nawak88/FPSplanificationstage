<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Services\PublicBesoinFormationPageService;

class BesoinNouveau extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.besoin-formation';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/besoins/nouveau';

    public function mount(): void
    {
        $this->pageData = app(
            PublicBesoinFormationPageService::class
        )->form(
            auth()->user()
        );
    }
}
