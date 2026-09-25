<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Services\PublicBesoinFormationPageService;

class BesoinSuiviRecherche extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.besoin-formation-suivi-recherche';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/besoins/suivi';

    public function mount(): void
    {
        $this->pageData = app(
            PublicBesoinFormationPageService::class
        )->suiviRecherche();
    }
}
