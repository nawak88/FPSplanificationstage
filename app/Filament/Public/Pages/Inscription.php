<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Http\Middleware\RequireMindefConnectAuthentication;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\PublicInscriptionPageService;

class Inscription extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.inscription';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/sessions/{session}/inscription';

    protected static string|array $routeMiddleware = [
        RequireMindefConnectAuthentication::class,
    ];

    public function mount(int|string $session): void
    {
        $record = SessionStage::query()->findOrFail($session);

        $this->pageData = app(
            PublicInscriptionPageService::class
        )->form(
            $record,
            auth()->user()
        );
    }
}
