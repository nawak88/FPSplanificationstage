<?php

use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Pages\Admission;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\InscriptionResource;
use Modules\FPSplanificationstage\Filament\Resources\Stagiaires\StagiaireResource;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel(
            'fpsplanificationstage'
        )
    );
});

function creerMarinPourHistorique(): Marin
{
    $suffixe =
        Str::lower(
            Str::random(6)
        );

    return Marin::withoutEvents(
        fn (): Marin =>
            Marin::withoutGlobalScopes()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),

                    'nom' =>
                        'DUPONT',

                    'prenom' =>
                        'Camille',

                    'nid' =>
                        'NID-' . $suffixe,

                    'matricule' =>
                        'MAT-' . $suffixe,

                    'email' =>
                        $suffixe
                        . '@example.test',
                ])
    );
}

function creerStagePourHistorique(
    string $suffixe = 'A'
): Stage {
    return Stage::create([
        'code_stage' =>
            'STG-HIST-' . $suffixe,

        'libelle_court' =>
            'Stage historique ' . $suffixe,

        'actif' =>
            true,
    ]);
}

function creerSessionPourHistorique(
    Stage $stage,
    string $debut,
    string $fin,
    string $statut = 'planifiee'
): SessionStage {
    return SessionStage::create([
        'stage_id' =>
            $stage->id,

        'debut' =>
            $debut,

        'fin' =>
            $fin,

        'capacite_max' =>
            20,

        'statut' =>
            $statut,
    ]);
}

it(
    'signale une nouvelle inscription lorsque le marin a déjà effectué le stage',
    function (): void {
        $marin =
            creerMarinPourHistorique();

        $stage =
            creerStagePourHistorique();

        $ancienneSession =
            creerSessionPourHistorique(
                $stage,
                '2026-01-05 09:00:00',
                '2026-01-09 17:00:00',
                'terminee'
            );

        $nouvelleSession =
            creerSessionPourHistorique(
                $stage,
                '2026-11-02 09:00:00',
                '2026-11-06 17:00:00'
            );

        $ancienneInscription =
            Inscription::create([
                'session_stage_id' =>
                    $ancienneSession->id,

                'stagiaire_id' =>
                    $marin->id,

                'presence' =>
                    'present',

                'statut' =>
                    'confirmee',

                'nemo_recu' =>
                    true,
            ]);

        $nouvelleInscription =
            Inscription::create([
                'session_stage_id' =>
                    $nouvelleSession->id,

                'stagiaire_id' =>
                    $marin->id,

                'statut' =>
                    'attente_nemo',

                'nemo_recu' =>
                    false,
            ]);

        expect(
            $ancienneInscription
                ->stage_deja_effectue
        )
            ->toBeFalse()
            ->and(
                $nouvelleInscription
                    ->stage_deja_effectue
            )
            ->toBeTrue();
    }
);

it(
    'ne signale pas une absence ni la participation à un autre stage',
    function (): void {
        $marin =
            creerMarinPourHistorique();

        $stageDemande =
            creerStagePourHistorique(
                'DEMANDE'
            );

        $autreStage =
            creerStagePourHistorique(
                'AUTRE'
            );

        $sessionAbsence =
            creerSessionPourHistorique(
                $stageDemande,
                '2026-01-12 09:00:00',
                '2026-01-16 17:00:00',
                'terminee'
            );

        $sessionAutreStage =
            creerSessionPourHistorique(
                $autreStage,
                '2026-01-19 09:00:00',
                '2026-01-23 17:00:00',
                'terminee'
            );

        $nouvelleSession =
            creerSessionPourHistorique(
                $stageDemande,
                '2026-11-09 09:00:00',
                '2026-11-13 17:00:00'
            );

        Inscription::create([
            'session_stage_id' =>
                $sessionAbsence->id,

            'stagiaire_id' =>
                $marin->id,

            'presence' =>
                'absent',

            'statut' =>
                'confirmee',

            'nemo_recu' =>
                true,
        ]);

        Inscription::create([
            'session_stage_id' =>
                $sessionAutreStage->id,

            'stagiaire_id' =>
                $marin->id,

            'presence' =>
                'present',

            'statut' =>
                'confirmee',

            'nemo_recu' =>
                true,
        ]);

        $nouvelleInscription =
            Inscription::create([
                'session_stage_id' =>
                    $nouvelleSession->id,

                'stagiaire_id' =>
                    $marin->id,

                'statut' =>
                    'attente_nemo',

                'nemo_recu' =>
                    false,
            ]);

        expect(
            $nouvelleInscription
                ->stage_deja_effectue
        )->toBeFalse();
    }
);

it(
    'avertit le marin après son inscription publique et conserve l alerte',
    function (): void {
        $marin =
            creerMarinPourHistorique();

        $stage =
            creerStagePourHistorique();

        $ancienneSession =
            creerSessionPourHistorique(
                $stage,
                '2026-02-02 09:00:00',
                '2026-02-06 17:00:00',
                'terminee'
            );

        $nouvelleSession =
            creerSessionPourHistorique(
                $stage,
                '2026-12-07 09:00:00',
                '2026-12-11 17:00:00'
            );

        Inscription::create([
            'session_stage_id' =>
                $ancienneSession->id,

            'stagiaire_id' =>
                $marin->id,

            'presence' =>
                'present',

            'statut' =>
                'confirmee',

            'nemo_recu' =>
                true,
        ]);

        $gestionnaire =
            User::factory()
                ->create([
                    'admin' =>
                        true,
                ]);

        $candidat =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),

                    'nom' =>
                        $marin->nom,

                    'prenom' =>
                        $marin->prenom,

                    'email' =>
                        $marin->email,
                ]);

        actingAs(
            $candidat
        );

        $response = post(
            route(
                'fpsplanificationstage.public.inscription.store',
                [
                    'session' =>
                        $nouvelleSession->id,
                ]
            ),
            [
                'nom' =>
                    $marin->nom,

                'prenom' =>
                    $marin->prenom,

                'nid' =>
                    $marin->nid,

                'matricule' =>
                    $marin->matricule,

                'unite' =>
                    'Bâtiment test',

                'email' =>
                    $marin->email,
            ]
        );

        $response
            ->assertRedirect(
                PlanningFormations::getUrl(
                    panel:
                        'fpsplanificationstage'
                )
            )
            ->assertSessionHas(
                'inscription_warning',
                fn (string $message): bool =>
                    str_contains(
                        $message,
                        'ne sera pas prioritaire'
                    )
            );

        $this->assertDatabaseHas(
            'inscriptions',
            [
                'session_stage_id' =>
                    $nouvelleSession->id,

                'stagiaire_id' =>
                    $marin->id,

                'stage_deja_effectue' =>
                    true,
            ]
        );

        expect(
            $gestionnaire
                ->notifications()
                ->count()
        )->toBe(1);
    }
);

it(
    'place le candidat non prioritaire après les autres dans Admission',
    function (): void {
        $marin =
            creerMarinPourHistorique();

        $stage =
            creerStagePourHistorique();

        $ancienneSession =
            creerSessionPourHistorique(
                $stage,
                '2026-03-02 09:00:00',
                '2026-03-06 17:00:00',
                'terminee'
            );

        $nouvelleSession =
            creerSessionPourHistorique(
                $stage,
                '2026-12-14 09:00:00',
                '2026-12-18 17:00:00'
            );

        Inscription::create([
            'session_stage_id' =>
                $ancienneSession->id,

            'stagiaire_id' =>
                $marin->id,

            'presence' =>
                'present',

            'statut' =>
                'confirmee',

            'nemo_recu' =>
                true,
        ]);

        $candidat =
            Inscription::create([
                'session_stage_id' =>
                    $nouvelleSession->id,

                'stagiaire_id' =>
                    $marin->id,

                'statut' =>
                    'confirmee',

                'nemo_recu' =>
                    true,
            ]);

        livewire(Admission::class)
            ->set(
                'sessionId',
                $nouvelleSession->id
            )
            ->assertSet(
                'candidateDecisions.'
                . $candidat->id,
                'ignorer'
            )
            ->assertSee(
                'Stage déjà effectué'
            )
            ->assertSee(
                'candidat non prioritaire'
            );
    }
);

it(
    'replace l historique des marins sous Admission en réutilisant RH',
    function (): void {
        expect(
            Admission::getNavigationGroup()
        )
            ->toBe('Inscriptions / Admission')
            ->and(
                InscriptionResource::getNavigationGroup()
            )
            ->toBe('Inscriptions / Admission')
            ->and(
                StagiaireResource::getNavigationGroup()
            )
            ->toBe('Inscriptions / Admission')
            ->and(
                InscriptionResource::getNavigationSort()
            )
            ->toBe(10)
            ->and(
                Admission::getNavigationSort()
            )
            ->toBe(20)
            ->and(
                StagiaireResource::getNavigationSort()
            )
            ->toBe(30)
            ->and(
                StagiaireResource::getNavigationLabel()
            )
            ->toBe('Historique')
            ->and(
                StagiaireResource::getModel()
            )
            ->toBe(Marin::class);
    }
);

it(
    'affiche Historique aux gestionnaires FPS sans exiger les droits RH',
    function (): void {
        $gestionnaire =
            User::factory()
                ->create();

        $permission =
            Permission::firstOrCreate([
                'name' =>
                    'fpsplanificationstage::gerer_le_module',

                'guard_name' =>
                    'web',
            ]);

        $gestionnaire->givePermissionTo(
            $permission
        );

        actingAs(
            $gestionnaire
        );

        expect(
            StagiaireResource::canViewAny()
        )->toBeTrue();

        $this->get(
            StagiaireResource::getUrl(
                'index'
            )
        )->assertSuccessful();
    }
);

it(
    'ne rend pas l historique personnel public',
    function (): void {
        expect(
            StagiaireResource::canViewAny()
        )->toBeFalse();

        $this->get(
            StagiaireResource::getUrl(
                'index'
            )
        )->assertForbidden();
    }
);
