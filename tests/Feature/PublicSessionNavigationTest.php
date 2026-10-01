<?php

use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinConfirmation;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinNouveau;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuivi;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuiviRecherche;
use Modules\FPSplanificationstage\Filament\Public\Pages\Inscription;
use Modules\FPSplanificationstage\Filament\Public\Pages\InscriptionConfirmation;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;

it('all migrated screens remain filament pages', function (): void {
    foreach ([
        SessionDetail::class,
        Inscription::class,
        InscriptionConfirmation::class,
        BesoinNouveau::class,
        BesoinSuiviRecherche::class,
        BesoinConfirmation::class,
        BesoinSuivi::class,
    ] as $pageClass) {
        expect(is_subclass_of($pageClass, Page::class))
            ->toBeTrue();
    }
});

it('uses resource forms instead of HTTP submission routes', function (): void {
    foreach ([
        'fpsplanificationstage.public.inscription.store',
        'fpsplanificationstage.public.besoin.store',
        'fpsplanificationstage.public.besoin.suivi.rechercher',
    ] as $name) {
        expect(Route::getRoutes()->getByName($name))->toBeNull();
    }
    $resource = \Modules\FPSplanificationstage\Filament\Resources\PortailFormations\PortailFormationResource::class;
    foreach ([Inscription::class, BesoinNouveau::class, BesoinSuiviRecherche::class] as $page) {
        expect($page::getResource())->toBe($resource);
    }
    $route = Route::getRoutes()->getByName($resource::getRouteBaseName(\Filament\Facades\Filament::getPanel('fpsplanificationstage')) . '.pdf');
    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('GET');
});
