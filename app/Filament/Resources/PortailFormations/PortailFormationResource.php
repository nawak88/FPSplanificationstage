<?php

namespace Modules\FPSplanificationstage\Filament\Resources\PortailFormations;

use Closure;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;
use Modules\FPSplanificationstage\Filament\Public\Pages;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Services\PublicInscriptionPdfDownload;

class PortailFormationResource extends Resource
{
    protected static ?string $model = Inscription::class;
    protected static ?string $slug = 'espace-stagiaire/planning-formations';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [
            'session' => Pages\SessionDetail::route('/sessions/{session}'),
            'inscription' => Pages\Inscription::route('/sessions/{session}/inscription'),
            'confirmation-inscription' => Pages\InscriptionConfirmation::route('/inscriptions/{code}/confirmation'),
            'besoin' => Pages\BesoinNouveau::route('/besoins/nouveau'),
            'recherche-besoin' => Pages\BesoinSuiviRecherche::route('/besoins/suivi'),
            'confirmation-besoin' => Pages\BesoinConfirmation::route('/besoins/{token}/confirmation'),
            'suivi-besoin' => Pages\BesoinSuivi::route('/besoins/{token}/suivi'),
        ];
    }

    public static function routes(Panel $panel, ?Closure $registerPageRoutes = null): void
    {
        parent::routes($panel, function () use ($registerPageRoutes): void {
            $registerPageRoutes();
            Route::get('/inscriptions/{code}/pdf', PublicInscriptionPdfDownload::class)->name('pdf');
        });
    }
}
