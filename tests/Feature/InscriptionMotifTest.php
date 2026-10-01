<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Public\Pages\Inscription as PublicInscriptionPage;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages\ListInscriptions;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel('fpsplanificationstage')
    );
});

function creerSessionPourMotifInscription(): SessionStage
{
    $stage = Stage::create([
        'code_stage' => 'STG-MOTIF-' . Str::lower(Str::random(6)),
        'libelle_court' => 'Stage motif inscription',
        'actif' => true,
    ]);

    return SessionStage::create([
        'stage_id' => $stage->getKey(),
        'debut' => '2027-02-01 08:00:00',
        'fin' => '2027-02-05 16:00:00',
        'capacite_max' => 20,
        'statut' => 'planifiee',
    ]);
}

it('propose exactement les motifs d inscription demandes', function (): void {
    expect(Inscription::motifInscriptionOptions())
        ->toBe([
            'cursus_specialite' => 'Cursus de spécialité',
            'depart_outre_mer' => 'Départ outre-mer',
            'par_unite_deficitaire' => 'PAR de l’unité déficitaire',
            'preparation_prochain_pam' => 'Préparation prochain PAM',
            'prerequis_bs_csup' => 'Prérequis BS ou CSUP',
            'autre' => 'Autre',
            'sans_objet' => 'Sans objet',
        ]);
});

it('affiche et enregistre le motif choisi pendant l inscription', function (): void {
    $session = creerSessionPourMotifInscription();
    $candidate = User::factory()->create([
        'sub' => 'mindef-' . Str::uuid(),
        'nom' => 'DURAND',
        'prenom' => 'Alice',
        'email' => 'alice.durand@example.test',
    ]);

    actingAs($candidate);

    get(
        PublicInscriptionPage::getUrl(
            ['session' => $session->getKey()],
            panel: 'fpsplanificationstage'
        )
    )
        ->assertSuccessful()
        ->assertSee('data.motif_inscription', false)
        ->assertSee('Cursus de spécialité')
        ->assertSee('Départ outre-mer')
        ->assertSee('PAR de l’unité déficitaire')
        ->assertSee('Préparation prochain PAM')
        ->assertSee('Prérequis BS ou CSUP')
        ->assertSee('Sans objet');

    \Pest\Livewire\livewire(\Modules\FPSplanificationstage\Filament\Public\Pages\Inscription::class, ['session' => $session->getKey()])->fillForm([
            'unite' => 'Unité test',
            'motif_inscription' => 'par_unite_deficitaire',
        ])->call('submit')->assertRedirect(
        PlanningFormations::getUrl(
            panel: 'fpsplanificationstage'
        )
    );

    $this->assertDatabaseHas('inscriptions', [
        'session_stage_id' => $session->getKey(),
        'candidat_user_id' => $candidate->getKey(),
        'motif_inscription' => 'par_unite_deficitaire',
    ]);
});

it('refuse un motif qui ne fait pas partie de la liste', function (): void {
    $session = creerSessionPourMotifInscription();
    $candidate = User::factory()->create([
        'sub' => 'mindef-' . Str::uuid(),
    ]);

    actingAs($candidate);

    \Pest\Livewire\livewire(\Modules\FPSplanificationstage\Filament\Public\Pages\Inscription::class, ['session' => $session->getKey()])->fillForm([
            'unite' => 'Unité test',
            'motif_inscription' => 'motif-inconnu',
        ])->call('submit')->assertHasFormErrors(['motif_inscription']);

    expect(Inscription::query()->count())->toBe(0);
});

it('conserve sans objet pour une ancienne inscription sans motif', function (): void {
    $session = creerSessionPourMotifInscription();

    $inscription = Inscription::create([
        'session_stage_id' => $session->getKey(),
        'candidat_nom' => 'ANCIEN',
        'candidat_prenom' => 'Dossier',
        'candidat_email' => 'ancien@example.test',
        'statut' => 'attente_nemo',
    ])->refresh();

    expect($inscription->motif_inscription)
        ->toBe('sans_objet')
        ->and($inscription->motif_inscription_label)
        ->toBe('Sans objet');
});

it('remplace la colonne priorite par le motif dans la liste', function (): void {
    $session = creerSessionPourMotifInscription();
    $inscription = Inscription::create([
        'session_stage_id' => $session->getKey(),
        'candidat_nom' => 'MARTIN',
        'candidat_prenom' => 'Louise',
        'candidat_email' => 'louise.martin@example.test',
        'motif_inscription' => 'preparation_prochain_pam',
        'statut' => 'attente_nemo',
    ]);

    actingAs(User::factory()->create([
        'admin' => true,
    ]));

    livewire(ListInscriptions::class)
        ->assertCanSeeTableRecords([$inscription])
        ->assertTableColumnExists(
            'motif_inscription',
            fn (TextColumn $column): bool =>
                $column->getLabel() === 'Motif',
            $inscription
        )
        ->assertTableColumnDoesNotExist('stage_deja_effectue')
        ->assertSee('Préparation prochain PAM');
});
