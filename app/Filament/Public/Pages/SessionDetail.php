<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\PublicSessionPageService;

class SessionDetail extends PublicPage
{
    protected string $view = 'fpsplanificationstage::filament.public.formation-detail';

    protected static ?string $slug = 'espace-stagiaire/planning-formations/sessions/{session}';

    public function mount(int|string $session): void
    {
        $record = SessionStage::query()->findOrFail($session);

        $this->pageData = app(
            PublicSessionPageService::class
        )->detail(
            $record
        );
    }
}
