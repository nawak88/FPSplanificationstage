<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinNouveau;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuivi;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuiviRecherche;
use Modules\FPSplanificationstage\Filament\Public\Pages\Inscription as InscriptionPage;
use Modules\FPSplanificationstage\Filament\Resources\PortailFormations\PortailFormationResource;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\PrerequisStage;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\InscriptionPdfService;
use Modules\RH\Models\Unite;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('fpsplanificationstage'));
});

function sessionPortailResource(): SessionStage
{
    $stage = Stage::create(['libelle_court' => 'Stage du portail', 'actif' => true, 'duree_jours' => 1]);
    return SessionStage::create([
        'stage_id' => $stage->id, 'debut' => now()->addMonth(),
        'fin' => now()->addMonth()->addDay(), 'statut' => 'planifiee', 'capacite_max' => 20,
    ]);
}

it('enregistre plusieurs besoins depuis le formulaire de la ressource', function (): void {
    $session = sessionPortailResource();
    $unite = Unite::factory()->create();
    $besoin = [
        'stage_id' => $session->stage_id, 'type_periode' => 'dates_fixes',
        'date_debut_souhaitee' => now()->addMonth()->startOfWeek()->addWeek()->format('Y-m-d'),
        'nombre_stagiaires' => 2,
    ];
    livewire(BesoinNouveau::class)->fillForm([
        'type_periode' => 'plage',
        'date_debut_souhaitee' => $besoin['date_debut_souhaitee'],
        'date_fin_souhaitee' => $besoin['date_debut_souhaitee'],
        'demandeur' => $unite->libelle_long, 'contact_nom' => 'Contact',
        'contact_email' => 'contact@example.test', 'besoins' => [$besoin, $besoin],
    ])->call('submit')->assertHasNoFormErrors()->assertRedirect();
    $this->assertDatabaseCount('besoin_formations', 2);
    expect(session('besoins_crees'))->toHaveCount(2);
});

it('retrouve un besoin uniquement avec sa reference et son adresse email', function (): void {
    $session = sessionPortailResource();
    $besoin = BesoinFormation::create([
        'stage_id' => $session->stage_id, 'demandeur' => 'Unité',
        'contact_nom' => 'Contact', 'contact_email' => 'contact@example.test',
        'type_periode' => 'dates_fixes', 'date_debut_souhaitee' => now()->addMonth()->startOfWeek()->addWeek(),
        'nombre_stagiaires' => 1, 'source' => 'portail', 'public_token' => (string) Illuminate\Support\Str::uuid(),
    ]);
    livewire(BesoinSuiviRecherche::class)->fillForm([
        'code_besoin' => $besoin->code_besoin, 'contact_email' => 'autre@example.test',
    ])->call('submit')->assertHasFormErrors(['code_besoin'])->assertNoRedirect();
    livewire(BesoinSuiviRecherche::class)->fillForm([
        'code_besoin' => strtolower($besoin->code_besoin), 'contact_email' => $besoin->contact_email,
    ])->call('submit')->assertHasNoFormErrors()->assertRedirect(
        BesoinSuivi::getUrl(['token' => $besoin->public_token], panel: 'fpsplanificationstage')
    );
});

it('conserve la limite des recherches de suivi', function (): void {
    $key = BesoinSuiviRecherche::class . ':127.0.0.1';
    RateLimiter::clear($key);
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }
    livewire(BesoinSuiviRecherche::class)->fillForm([
        'code_besoin' => 'BES-INCONNU', 'contact_email' => 'contact@example.test',
    ])->call('submit')->assertHasErrors(['data'])->assertNoRedirect();
    RateLimiter::clear($key);
});

it('valide les prerequis et la justification de derogation avant la candidature', function (): void {
    actingAs(User::factory()->create(['email' => 'candidat@example.test']));
    $session = sessionPortailResource();
    PrerequisStage::create([
        'stage_id' => $session->stage_id, 'libelle' => 'Brevet obligatoire',
        'obligatoire' => true, 'actif' => true,
    ]);
    $page = livewire(InscriptionPage::class, ['session' => $session->id])->fillForm([
        'unite' => 'Unité', 'motif_inscription' => 'autre',
    ])->call('submit')->assertHasFormErrors(['demande_derogation']);
    $this->assertDatabaseCount('inscriptions', 0);
    $page->fillForm(['demande_derogation' => true])->call('submit')
        ->assertHasFormErrors(['derogation_motif']);
    $page->fillForm(['derogation_motif' => 'Expérience équivalente'])->call('submit')
        ->assertHasNoFormErrors()->assertRedirect();
    $this->assertDatabaseHas('inscriptions', [
        'session_stage_id' => $session->id, 'statut' => 'attente_derogation',
    ]);
    $this->assertDatabaseHas('inscription_prerequis', ['respecte' => false]);
});

it('refuse une deuxieme candidature active sur la meme session', function (): void {
    actingAs(User::factory()->create(['email' => 'candidat@example.test']));
    $session = sessionPortailResource();
    $data = ['unite' => 'Unité', 'motif_inscription' => 'autre'];
    livewire(InscriptionPage::class, ['session' => $session->id])->fillForm($data)
        ->call('submit')->assertHasNoFormErrors();
    livewire(InscriptionPage::class, ['session' => $session->id])->fillForm($data)
        ->call('submit')->assertHasFormErrors(['email'])->assertNoRedirect();
    $this->assertDatabaseCount('inscriptions', 1);
});

it('refuse un visiteur sur le formulaire de candidature', function (): void {
    $session = sessionPortailResource();
    livewire(InscriptionPage::class, ['session' => $session->id])->assertForbidden();
});

it('protege le PDF de la ressource par une signature temporaire', function (): void {
    $session = sessionPortailResource();
    $inscription = Inscription::create([
        'session_stage_id' => $session->id, 'candidat_nom' => 'DUPONT',
        'candidat_prenom' => 'Alice', 'candidat_email' => 'alice@example.test', 'statut' => 'attente_nemo',
    ]);
    $route = PortailFormationResource::getRouteBaseName(Filament::getPanel('fpsplanificationstage')) . '.pdf';
    $parameters = ['code' => $inscription->code_inscription];
    $this->get(route($route, $parameters))->assertForbidden();
    $this->get(URL::temporarySignedRoute($route, now()->subMinute(), $parameters, false))->assertForbidden();
    $this->mock(InscriptionPdfService::class)->shouldReceive('render')->once()->andReturn('%PDF-test');
    $this->get(URL::temporarySignedRoute($route, now()->addHour(), $parameters, false))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertContent('%PDF-test');
});
