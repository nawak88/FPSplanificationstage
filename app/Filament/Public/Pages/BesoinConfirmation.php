<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Services\PublicBesoinFormationPageService;

class BesoinConfirmation extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.besoin-formation-confirmation';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/besoins/{token}/confirmation';

    public function mount(string $token): void
    {
        $this->pageData = app(
            PublicBesoinFormationPageService::class
        )->confirmation(
            $token
        );
    }
}
