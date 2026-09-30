<?php

use Filament\Pages\Page;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;

it('the training planning remains in the main filament panel', function (): void {
    expect(is_subclass_of(PlanningFormations::class, Page::class))
        ->toBeTrue();

    $source = file_get_contents(
        dirname(__DIR__, 2)
        . '/app/Providers/Filament/FilamentPanelProvider.php'
    );

    expect($source)
        ->toContain("->id(\n                'fpsplanificationstage'")
        ->toContain("'app/Filament/Pages'")
        ->toContain("'app/Filament/Public/Pages'")
        ->not->toContain('->pages([')
        ->not->toContain('PublicFilamentPanelProvider');
});

it('the standalone formations panel has been removed', function (): void {
    $moduleRoot = dirname(__DIR__, 2);

    expect(
        file_exists(
            $moduleRoot
            . '/app/Providers/Filament/PublicFilamentPanelProvider.php'
        )
    )->toBeFalse();

    expect(
        file_exists(
            $moduleRoot
            . '/app/Filament/Public/Pages/PlanningPublic.php'
        )
    )->toBeFalse();
});
