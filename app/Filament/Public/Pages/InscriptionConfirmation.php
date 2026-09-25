<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Services\PublicInscriptionPageService;

class InscriptionConfirmation extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.inscription-confirmation';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/inscriptions/{code}/confirmation';

    public function mount(string $code): void
    {
        $this->pageData = app(
            PublicInscriptionPageService::class
        )->confirmation(
            $code
        );
    }
}
