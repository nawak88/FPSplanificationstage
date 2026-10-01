<?php

use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\InstructeurResource;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Pages\ListInstructeurs;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\InstructeurManuelService;
use Modules\RH\Models\Marin;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel('fpsplanificationstage')
    );
});

function creerUtilisateurGestionnaireInstructeurs(
    bool $peutGerer = true
): User {
    $user = User::factory()->create();

    $permissions = [
        Permission::firstOrCreate([
            'name' => 'rh::marins.index',
            'guard_name' => 'web',
        ]),
        Permission::firstOrCreate([
            'name' => 'rh::voir_tous_les_marins',
            'guard_name' => 'web',
        ]),
    ];

    if ($peutGerer) {
        $permissions[] = Permission::firstOrCreate([
            'name' =>
                'fpsplanificationstage::gerer_le_module',
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo($permissions);

    return $user;
}

function creerMarinPourAjoutManuel(): Marin
{
    return Marin::withoutEvents(
        fn (): Marin => Marin::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'nom' => 'DUPONT',
            'prenom' => 'Jeanne',
            'nid' => 'NID-MANUEL-001',
            'matricule' => 'MAT-MANUEL-001',
            'email' => 'jeanne.dupont@example.test',
        ])
    );
}

it(
    'reserve l ajout manuel aux gestionnaires du module',
    function (): void {
        actingAs(
            creerUtilisateurGestionnaireInstructeurs(false)
        );

        livewire(ListInstructeurs::class)
            ->assertActionHidden('ajouterInstructeur');

        expect(
            fn () => app(
                InstructeurManuelService::class
            )->ajouter(1, [1])
        )->toThrow(AuthorizationException::class);

        actingAs(
            creerUtilisateurGestionnaireInstructeurs()
        );

        livewire(ListInstructeurs::class)
            ->assertActionVisible('ajouterInstructeur');
    }
);

it(
    'valide les informations obligatoires de l ajout manuel',
    function (): void {
        actingAs(
            creerUtilisateurGestionnaireInstructeurs()
        );

        livewire(ListInstructeurs::class)
            ->callAction('ajouterInstructeur', [])
            ->assertHasActionErrors([
                'marin_id' => 'required',
                'stage_ids' => 'required',
            ]);
    }
);

it(
    'ajoute un marin comme instructeur sur plusieurs stages',
    function (): void {
        actingAs(
            creerUtilisateurGestionnaireInstructeurs()
        );

        $marin = creerMarinPourAjoutManuel();

        $premierStage = Stage::create([
            'code_stage' => 'STG-MANUEL-001',
            'libelle_court' => 'Premier stage manuel',
            'actif' => true,
        ]);

        $secondStage = Stage::create([
            'code_stage' => 'STG-MANUEL-002',
            'libelle_court' => 'Second stage manuel',
            'actif' => true,
        ]);

        livewire(ListInstructeurs::class)
            ->callAction('ajouterInstructeur', [
                'marin_id' => $marin->getKey(),
                'stage_ids' => [
                    $premierStage->getKey(),
                    $secondStage->getKey(),
                ],
                'role' => 'principal',
                'commentaire' => '  Formateur confirmé  ',
            ])
            ->assertHasNoActionErrors();

        foreach ([$premierStage, $secondStage] as $stage) {
            $this->assertDatabaseHas('instructeur_stage', [
                'instructeur_id' => $marin->getKey(),
                'stage_id' => $stage->getKey(),
                'role' => 'principal',
                'actif' => true,
                'commentaire' => 'Formateur confirmé',
                'source' => 'manuel',
            ]);
        }

        expect(
            InstructeurResource::getEloquentQuery()
                ->whereKey($marin->getKey())
                ->exists()
        )->toBeTrue();

        livewire(ListInstructeurs::class)
            ->callAction('ajouterInstructeur', [
                'marin_id' => $marin->getKey(),
                'stage_ids' => [$premierStage->getKey()],
                'role' => 'suppleant',
                'commentaire' => null,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('instructeur_stage', 2);

        $this->assertDatabaseHas('instructeur_stage', [
            'instructeur_id' => $marin->getKey(),
            'stage_id' => $premierStage->getKey(),
            'role' => 'suppleant',
            'commentaire' => null,
            'source' => 'manuel',
        ]);
    }
);


it('cree la fiche RH du marin inconnu et ses habilitations', function (): void {
    Illuminate\Support\Facades\Queue::fake();
    actingAs(creerUtilisateurGestionnaireInstructeurs());
    $stage = Stage::create(['libelle_court' => 'Stage nouveau marin', 'actif' => true]);

    livewire(ListInstructeurs::class)
        ->callAction('ajouterInstructeur', [
            'nouveau_marin' => true,
            'nom' => 'MARTIN',
            'prenom' => 'Alice',
            'nid' => 'NID-NOUVEAU-001',
            'stage_ids' => [$stage->getKey()],
            'role' => 'principal',
        ])
        ->assertHasNoActionErrors();

    $marin = Marin::withoutGlobalScopes()->where('nid', 'NID-NOUVEAU-001')->sole();
    expect($marin->nom)->toBe('MARTIN');
    $this->assertDatabaseHas('instructeur_stage', [
        'instructeur_id' => $marin->id,
        'stage_id' => $stage->id,
        'role' => 'principal',
    ]);
    Illuminate\Support\Facades\Queue::assertPushed(Modules\RH\Jobs\ConfirmMarinUuidJob::class);
});

it('exige l identite du marin absent de RH', function (): void {
    actingAs(creerUtilisateurGestionnaireInstructeurs());
    livewire(ListInstructeurs::class)
        ->callAction('ajouterInstructeur', ['nouveau_marin' => true])
        ->assertHasActionErrors([
            'nom' => 'required', 'prenom' => 'required', 'nid' => 'required',
            'stage_ids' => 'required',
        ]);
});

it('refuse un NID deja connu sans creer de doublon', function (): void {
    actingAs(creerUtilisateurGestionnaireInstructeurs());
    $marin = creerMarinPourAjoutManuel();
    $stage = Stage::create(['libelle_court' => 'Stage doublon', 'actif' => true]);
    livewire(ListInstructeurs::class)
        ->callAction('ajouterInstructeur', [
            'nouveau_marin' => true,
            'nom' => 'Autre', 'prenom' => 'Identite',
            'nid' => strtolower($marin->nid),
            'stage_ids' => [$stage->id], 'role' => 'indifferent',
        ])
        ->assertHasActionErrors(['nid']);
    $this->assertDatabaseCount('rh_marins', 1);
    $this->assertDatabaseCount('instructeur_stage', 0);
});
