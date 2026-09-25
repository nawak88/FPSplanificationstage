<?php

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);
uses()->group('FPSplanificationstage');

it('does not register a second public filament panel provider', function (): void {
    $moduleRoot = dirname(__DIR__, 2);

    $module = json_decode(
        file_get_contents($moduleRoot . '/module.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($module['providers'] ?? [])
        ->not->toContain(
            'Modules\\FPSplanificationstage\\Providers\\Filament\\PublicFilamentPanelProvider'
        );
});

it('keeps html preparation out of controllers and generates panel urls', function (): void {
    $moduleRoot = dirname(__DIR__, 2);

    foreach (
        glob(
            $moduleRoot
            . '/app/Http/Controllers/*.php'
        )
        as $controller
    ) {
        $source = file_get_contents(
            $controller
        );

        expect($source)
            ->not->toContain('return view(')
            ->not->toContain('Illuminate\\View\\View');
    }

    foreach (
        [
            '/app',
            '/resources',
            '/routes',
        ]
        as $directory
    ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $moduleRoot . $directory,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($files as $file) {
            if (
                ! $file->isFile()
                || ! in_array(
                    $file->getExtension(),
                    [
                        'php',
                        'blade.php',
                    ],
                    true
                )
            ) {
                continue;
            }

            expect(
                file_get_contents(
                    $file->getPathname()
                )
            )->not->toContain('/apps/');
        }
    }

    foreach (
        [
            '/app/Services/PublicBesoinFormationPageService.php',
            '/app/Services/PublicInscriptionPageService.php',
            '/app/Services/PublicSessionPageService.php',
        ]
        as $service
    ) {
        expect(
            file_exists(
                $moduleRoot . $service
            )
        )->toBeTrue();
    }
});

it('registers all detail pages below the main training planning', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes());

    foreach ([
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/sessions/{session}',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/sessions/{session}/inscription',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/inscriptions/{code}/confirmation',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/besoins/nouveau',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/besoins/suivi',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/besoins/{token}/confirmation',
        'apps/fpsplanificationstage/espace-stagiaire/planning-formations/besoins/{token}/suivi',
    ] as $uri) {
        expect(
            $routes->contains(
                fn ($route): bool =>
                    $route->uri() === $uri
                    && in_array('GET', $route->methods(), true)
            )
        )->toBeTrue("Route GET manquante : {$uri}");
    }
});

it('removes the old apps formations entry point', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes());

    expect(
        $routes->contains(
            fn ($route): bool =>
                $route->uri() === 'apps/formations'
                && in_array('GET', $route->methods(), true)
        )
    )->toBeFalse();
});

it('supports several training needs in one public submission', function (): void {
    $moduleRoot = dirname(__DIR__, 2);

    $form = file_get_contents(
        $moduleRoot
        . '/resources/views/filament/public/besoin-formation.blade.php'
    );

    $controller = file_get_contents(
        $moduleRoot
        . '/app/Http/Controllers/PublicBesoinFormationController.php'
    );

    expect($form)
        ->toContain('id="add-besoin-stage"')
        ->toContain('name="besoins[')
        ->toContain('besoin-stage-template');

    expect($controller)
        ->toContain("'besoins' => [")
        ->toContain('DB::transaction(')
        ->toContain("\$validated['besoins']");
});

it('uses the three business planning modes without public priority', function (): void {
    $moduleRoot =
        dirname(
            __DIR__,
            2
        );

    $form =
        file_get_contents(
            $moduleRoot
            . '/resources/views/filament/public/besoin-formation.blade.php'
        );

    $controller =
        file_get_contents(
            $moduleRoot
            . '/app/Http/Controllers/PublicBesoinFormationController.php'
        );

    $bulkPlanner =
        file_get_contents(
            $moduleRoot
            . '/app/Services/BesoinFormationBulkPlanner.php'
        );

    $groupedPlanner =
        file_get_contents(
            $moduleRoot
            . '/app/Services/BesoinFormationGroupedPlanner.php'
        );

    expect($form)
        ->toContain('Date de début imposée')
        ->toContain('Période disponible')
        ->toContain('Période de démarrage')
        ->toContain('value="plage_demarrage"')
        ->not->toContain('Priorité')
        ->not->toContain('[priorite]');

    expect(
        substr_count(
            $form,
            'data-period-type style="grid-column:1/-1;"'
        )
    )->toBe(2);

    expect($controller)
        ->toContain("'plage_demarrage'")
        ->not->toContain("'besoins.*.priorite'");

    expect($bulkPlanner)
        ->toContain('PRIORITE_NEUTRALISEE_V1');

    expect($groupedPlanner)
        ->toContain('PRIORITE_NEUTRALISEE_V1')
        ->toContain('return 0;');
});
