<?php

use Filament\Facades\Filament;
use Filament\PanelProvider;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FPSplanificationstage\Filament\Pages\Admission;
use Modules\FPSplanificationstage\Filament\Pages\Dashboard;
use Modules\FPSplanificationstage\Filament\Pages\EspaceInstructeur;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Pages\ReservationsSalles;
use Modules\FPSplanificationstage\Filament\Pages\Statistiques;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinConfirmation;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinNouveau;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuivi;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuiviRecherche;
use Modules\FPSplanificationstage\Filament\Public\Pages\Inscription;
use Modules\FPSplanificationstage\Filament\Public\Pages\InscriptionConfirmation;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Filament\Widgets\PlanningCalendar;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Providers\Filament\FilamentPanelProvider;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);
uses()->group('FPSplanificationstage');

it('page metadata matches espace stagiaire', function () {
    expect(PlanningFormations::getNavigationLabel())->toBe('Planning des formations')
        ->and(PlanningFormations::getNavigationGroup())->toBe('Espace stagiaire');
});

it('discovers every module page in the fpsplanificationstage panel', function () {
    $panel = Filament::getPanel('fpsplanificationstage');

    expect($panel->getPageDirectories())
        ->toContain(module_path('FPSplanificationstage', 'app/Filament/Pages'));
    expect($panel->getPages())->toContain(
        Dashboard::class, EspaceInstructeur::class, Statistiques::class,
        Admission::class, PlanningFormations::class, ReservationsSalles::class
    );
    $resource = \Modules\FPSplanificationstage\Filament\Resources\PortailFormations\PortailFormationResource::class;
    expect($panel->getResources())->toContain($resource);
    $pages = array_map(fn ($page) => $page->getPage(), $resource::getPages());
    expect($pages)->toContain(
        SessionDetail::class, Inscription::class, InscriptionConfirmation::class,
        BesoinNouveau::class, BesoinSuiviRecherche::class,
        BesoinConfirmation::class, BesoinSuivi::class
    );
});

it('uses the native filament panel provider', function () {
    $parent = (new ReflectionClass(
        FilamentPanelProvider::class
    ))->getParentClass();

    expect($parent?->getName())->toBe(PanelProvider::class);
});

it('page uses native filament schema and guava calendar', function () {
    $page = new PlanningFormations();

    expect(method_exists($page, 'content'))->toBeTrue()
        ->and($page->content(resolve(\Filament\Schemas\Schema::class)))->toBeInstanceOf(\Filament\Schemas\Schema::class);

    $reflection = new ReflectionClass(PlanningFormations::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)
        ->toContain('Filament\\Schemas\\Schema')
        ->toContain('Livewire::make(PlanningCalendar::class')
        ->toContain('searchTerm');
});

it('calendar widget keeps live search filtering', function () {
    $reflection = new ReflectionClass(PlanningCalendar::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)
        ->toContain('public ?string $searchTerm =')
        ->toContain('this->searchTerm')
        ->toContain("whereHas('stage'");
});

it('shows every session when all statuses are selected', function () {
    app('url')->resolveMissingNamedRoutesUsing(
        fn (): string => '/testing/session-stages'
    );

    $stage = Stage::create([
        'code_stage' => 'STG-ALL-STATUSES',
        'libelle_court' => 'Tous les statuts',
        'actif' => true,
    ]);

    foreach (['brouillon', 'planifiee', 'confirmee', 'annulee', 'terminee'] as $status) {
        SessionStage::create([
            'stage_id' => $stage->id,
            'debut' => '2026-09-15 09:00:00',
            'fin' => '2026-09-15 17:00:00',
            'statut' => $status,
        ]);
    }

    $widget = new PlanningCalendar();
    $widget->statutFilter = '';

    $method = (new ReflectionClass($widget))->getMethod('getEvents');
    $events = $method->invoke(
        $widget,
        new FetchInfo([
            'startStr' => '2026-09-01T00:00:00',
            'endStr' => '2026-10-01T00:00:00',
        ])
    );

    expect($events)->toHaveCount(5);
});
