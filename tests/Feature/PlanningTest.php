<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\PlanningSessionStages;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Filament\Widgets\SessionStageCalendar;
use Modules\FPSplanificationstage\Filament\Widgets\TableauBordSemaine;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel('fpsplanificationstage')
    );
});

it('integre le planning a la ressource des sessions', function (): void {
    expect(is_subclass_of(PlanningSessionStages::class, Page::class))
        ->toBeTrue()
        ->and(PlanningSessionStages::getResource())
        ->toBe(SessionStageResource::class)
        ->and(
            SessionStageResource::getPages()['planning']->getPage()
        )
        ->toBe(PlanningSessionStages::class);

    $page = new PlanningSessionStages();

    expect($page->content(resolve(\Filament\Schemas\Schema::class)))
        ->toBeInstanceOf(\Filament\Schemas\Schema::class);

    $moduleRoot = dirname(__DIR__, 2);

    expect($moduleRoot . '/app/Filament/Pages/Planning.php')
        ->not->toBeFile()
        ->and(
            $moduleRoot
            . '/resources/views/filament/pages/planning.blade.php'
        )
        ->not->toBeFile();
});

it('conserve les deux entrees de navigation des sessions', function (): void {
    $labels = collect(SessionStageResource::getNavigationItems())
        ->map(fn ($item): string => $item->getLabel())
        ->all();

    expect($labels)->toBe([
        'Sessions de stages',
        'Planning / Calendrier',
    ]);
});

it('affiche la page planning de la ressource', function (): void {
    actingAs(User::factory()->create());

    get(
        SessionStageResource::getUrl(
            'planning',
            panel: 'fpsplanificationstage'
        )
    )
        ->assertSuccessful()
        ->assertSee('Planning / Calendrier')
        ->assertSee('Filtres')
        ->assertSee('Créer une session');
});

it('utilise la nouvelle page planning depuis le tableau de bord', function (): void {
    actingAs(User::factory()->create());

    $widget = new TableauBordSemaine();
    $widget->currentDate = now()->format('Y-m-d');

    expect(
        $widget->dashboardData()['urls']['planning']
    )->toBe(
        SessionStageResource::getUrl(
            'planning',
            panel: 'fpsplanificationstage'
        )
    );
});

it('affiche les sessions actives dans le calendrier de gestion', function (): void {
    $stage = Stage::create([
        'code_stage' => 'STG-PLANNING-TEST',
        'libelle_court' => 'Formation planifiée',
        'actif' => true,
    ]);

    SessionStage::create([
        'stage_id' => $stage->id,
        'debut' => '2026-09-15 09:00:00',
        'fin' => '2026-09-15 17:00:00',
        'statut' => 'planifiee',
    ]);

    SessionStage::create([
        'stage_id' => $stage->id,
        'debut' => '2026-09-16 09:00:00',
        'fin' => '2026-09-16 17:00:00',
        'statut' => 'annulee',
    ]);

    $widget = new SessionStageCalendar();
    $events = (new ReflectionMethod($widget, 'getEvents'))->invoke(
        $widget,
        new FetchInfo([
            'startStr' => '2026-09-01T00:00:00',
            'endStr' => '2026-10-01T00:00:00',
        ])
    );

    expect($events)
        ->toHaveCount(1)
        ->and($events[0]->getTitle())
        ->toBe('STG-PLANNING-TEST — Formation planifiée');

    $widget->viewMode = 'week';

    expect($widget->getCalendarView())
        ->toBe(CalendarViewType::TimeGridWeek)
        ->and($widget->getOptions())
        ->toMatchArray([
            'hiddenDays' => [0, 6],
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '16:00:00',
        ]);
});

it('permet de filtrer explicitement les sessions annulees', function (): void {
    $stage = Stage::create([
        'libelle_court' => 'Formation annulée',
        'actif' => true,
    ]);

    SessionStage::create([
        'stage_id' => $stage->id,
        'debut' => '2026-09-16 09:00:00',
        'fin' => '2026-09-16 17:00:00',
        'statut' => 'annulee',
    ]);

    $widget = new SessionStageCalendar();
    $widget->statutFilter = 'annulee';

    $events = (new ReflectionMethod($widget, 'getEvents'))->invoke(
        $widget,
        new FetchInfo([
            'startStr' => '2026-09-01T00:00:00',
            'endStr' => '2026-10-01T00:00:00',
        ])
    );

    expect($events)->toHaveCount(1);
});
