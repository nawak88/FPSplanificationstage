<?php

use Illuminate\Support\Facades\Route;
use Modules\FPSplanificationstage\Http\Controllers\PublicBesoinFormationController;
use Modules\FPSplanificationstage\Http\Controllers\PublicInscriptionController;
use Modules\FPSplanificationstage\Http\Middleware\RequireMindefConnectAuthentication;

/*
|--------------------------------------------------------------------------
| Actions du portail de formation
|--------------------------------------------------------------------------
|
| Les pages GET sont de vraies pages Filament du panel
| fpsplanificationstage. Les actions HTTP utilisent des routes Laravel
| nommées afin de rester indépendantes du préfixe configuré du panel.
*/

Route::post(
    '/fpsplanificationstage/espace-stagiaire/planning-formations/besoins/nouveau',
    [
        PublicBesoinFormationController::class,
        'store',
    ]
)
    ->middleware('throttle:20,1')
    ->name('fpsplanificationstage.public.besoin.store');

Route::post(
    '/fpsplanificationstage/espace-stagiaire/planning-formations/besoins/suivi',
    [
        PublicBesoinFormationController::class,
        'rechercherSuivi',
    ]
)
    ->middleware('throttle:10,1')
    ->name('fpsplanificationstage.public.besoin.suivi.rechercher');

Route::post(
    '/fpsplanificationstage/espace-stagiaire/planning-formations/sessions/{session}/inscription',
    [
        PublicInscriptionController::class,
        'store',
    ]
)
    ->middleware(
        RequireMindefConnectAuthentication::class
    )
    ->name('fpsplanificationstage.public.inscription.store');

Route::get(
    '/fpsplanificationstage/espace-stagiaire/planning-formations/inscriptions/{code}/pdf',
    [
        PublicInscriptionController::class,
        'pdf',
    ]
)->name('fpsplanificationstage.public.inscription.pdf');
