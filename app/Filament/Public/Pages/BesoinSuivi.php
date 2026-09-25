<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Services\PublicBesoinFormationPageService;

class BesoinSuivi extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.besoin-formation-suivi';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/besoins/{token}/suivi';

    public function mount(string $token): void
    {
        $this->pageData = app(
            PublicBesoinFormationPageService::class
        )->suivi(
            $token
        );
    }
}
