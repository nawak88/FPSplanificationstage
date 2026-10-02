<?php

use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinNouveau;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\BesoinPeriodeService;
use Modules\FPSplanificationstage\Services\BesoinFormationGroupedPlanner;
use Modules\FPSplanificationstage\Services\BesoinFormationPlanner;
use Modules\FPSplanificationstage\Models\Salle;
use Modules\RH\Models\Marin;
use Modules\RH\Models\Unite;

use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class)->group('FPSplanificationstage');

beforeEach(function () {
    \Illuminate\Support\Facades\Queue::fake();
    Filament::setCurrentPanel(Filament::getPanel('fpsplanificationstage'));
    $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
});

it('planifie une session entière dans la disponibilité ou après une indisponibilité en cours', function (string $type, string $expectedStart) {
    $payload = expressionPeriodePayload($type);
    if ($type === 'indisponibilite') {
        $payload['date_debut_souhaitee'] = '2026-10-05';
    }
    livewire(BesoinNouveau::class)->fillForm($payload)->call('submit')->assertHasNoFormErrors();
    $besoin = BesoinFormation::first();
    $marin = Marin::factory()->create();
    $besoin->stage->instructeurs()->attach($marin, ['actif' => true]);
    Salle::create(['nom' => 'Salle formation', 'capacite' => 30, 'actif' => true]);

    $result = app(BesoinFormationPlanner::class)->plan($besoin);
    expect($result['success'])->toBeTrue();
    $session = $besoin->fresh()->sessionStage;
    expect($session->debut->format('Y-m-d'))->toBe($expectedStart);
    if ($type === 'plage') {
        expect($session->fin->lte(Carbon::parse('2026-10-16')->endOfDay()))->toBeTrue();
    } else {
        expect(BesoinPeriodeService::avoidsUnavailablePeriod($besoin, $session->debut, $session->fin))->toBeTrue();
    }
})->with([
    ['plage', '2026-10-12'],
    ['indisponibilite', '2026-10-19'],
]);

function expressionPeriodePayload(string $type, int $duration = 2): array
{
    $unite = Unite::factory()->create();
    $stage = Stage::create(['libelle_court' => 'Stage période', 'actif' => true, 'duree_jours' => $duration]);

    return [
        'demandeur' => $unite->libelle_long,
        'contact_nom' => 'Contact', 'contact_email' => 'contact@example.test',
        'type_periode' => $type,
        'date_debut_souhaitee' => '2026-10-12', 'date_fin_souhaitee' => '2026-10-16',
        'besoins' => [
            ['stage_id' => $stage->id, 'nombre_stagiaires' => 2],
            ['stage_id' => $stage->id, 'nombre_stagiaires' => 3],
        ],
    ];
}

it('applique la période du demandeur à tous les stages', function (string $type) {
    livewire(BesoinNouveau::class)->fillForm(expressionPeriodePayload($type))
        ->call('submit')->assertHasNoFormErrors()->assertRedirect();

    expect(BesoinFormation::count())->toBe(2);
    foreach (BesoinFormation::all() as $besoin) {
        expect($besoin->type_periode)->toBe($type)
            ->and($besoin->date_debut_souhaitee->format('Y-m-d'))->toBe('2026-10-12')
            ->and($besoin->date_fin_souhaitee->format('Y-m-d'))->toBe('2026-10-16');
    }
})->with(['plage', 'indisponibilite']);

it('refuse une disponibilité trop courte pour un des stages sans créer de besoin', function () {
    livewire(BesoinNouveau::class)->fillForm(expressionPeriodePayload('plage', 6))
        ->call('submit')->assertHasFormErrors()->assertNoRedirect();
    expect(BesoinFormation::count())->toBe(0);
});

it('refuse une fin de période antérieure au début', function () {
    $payload = expressionPeriodePayload('indisponibilite');
    $payload['date_fin_souhaitee'] = '2026-10-11';
    livewire(BesoinNouveau::class)->fillForm($payload)->call('submit')
        ->assertHasFormErrors(['date_fin_souhaitee'])->assertNoRedirect();
    expect(BesoinFormation::count())->toBe(0);
});

it('exclut tout chevauchement avec l indisponibilité dans le moteur groupé', function () {
    $payload = expressionPeriodePayload('indisponibilite');
    livewire(BesoinNouveau::class)->fillForm($payload)->call('submit')->assertHasNoFormErrors();
    $besoin = BesoinFormation::first();
    $planner = app(BesoinFormationGroupedPlanner::class);
    $eligible = new ReflectionMethod($planner, 'eligibleNeeds');

    foreach ([
        ['2026-10-08 08:00', '2026-10-09 16:00', true],
        ['2026-10-09 08:00', '2026-10-12 16:00', false],
        ['2026-10-12 08:00', '2026-10-13 16:00', false],
        ['2026-10-16 08:00', '2026-10-19 16:00', false],
        ['2026-10-19 08:00', '2026-10-20 16:00', true],
    ] as [$start, $end, $expected]) {
        expect($eligible->invoke($planner, collect([$besoin]), Carbon::parse($start), Carbon::parse($end))->isNotEmpty())
            ->toBe($expected);
    }

    $candidates = (new ReflectionMethod($planner, 'candidateDates'))
        ->invoke($planner, collect([$besoin]), 2.0, 20, 1);
    expect($candidates)->not->toBeEmpty();
    foreach ($candidates as $candidate) {
        expect(BesoinPeriodeService::avoidsUnavailablePeriod($besoin, $candidate['debut'], $candidate['fin']))->toBeTrue();
    }
});
