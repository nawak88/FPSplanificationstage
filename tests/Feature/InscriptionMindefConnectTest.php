<?php

use App\Models\Permission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Public\Pages\Inscription as PublicInscriptionPage;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages\ListInscriptions;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\MarinDepuisInscriptionService;
use Modules\FPSplanificationstage\Services\PublicInscriptionPageService;
use Modules\RH\Models\Brevet;
use Modules\RH\Models\Grade;
use Modules\RH\Models\Marin;
use Modules\RH\Models\Specialite;
use Modules\RH\Models\Unite;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
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

function creerSessionPourInscriptionMindef(): SessionStage
{
    $suffixe = Str::lower(
        Str::random(6)
    );

    $stage = Stage::create([
        'code_stage' =>
            'STG-MINDEF-' . $suffixe,

        'libelle_court' =>
            'Stage Mindef ' . $suffixe,

        'actif' =>
            true,
    ]);

    return SessionStage::create([
        'stage_id' =>
            $stage->id,

        'debut' =>
            '2027-01-11 09:00:00',

        'fin' =>
            '2027-01-15 17:00:00',

        'capacite_max' =>
            20,

        'statut' =>
            'planifiee',
    ]);
}

it(
    'redirige vers la connexion locale lorsque MindefConnect n est pas configure',
    function (): void {
        config()->set(
            'services.keycloak.client_id',
            null
        );

        $session =
            creerSessionPourInscriptionMindef();

        get(
            PublicInscriptionPage::getUrl(
                [
                    'session' =>
                        $session->id,
                ],
                panel:
                    'fpsplanificationstage'
            )
        )->assertRedirect(
            route(
                'login'
            )
        );

    }
);

it(
    'laisse un utilisateur deja authentifie acceder directement a l inscription',
    function (): void {
        $session =
            creerSessionPourInscriptionMindef();

        $compteLocal =
            User::factory()
                ->create([
                    'sub' =>
                        null,
                ]);

        actingAs(
            $compteLocal
        );

        get(
            PublicInscriptionPage::getUrl(
                [
                    'session' =>
                        $session->id,
                ],
                panel:
                    'fpsplanificationstage'
            )
        )
            ->assertSuccessful()
            ->assertSee(
                $compteLocal->email
            );
    }
);

it(
    'redirige directement vers MindefConnect lorsqu il est configure',
    function (): void {
        config()->set(
            'services.keycloak',
            [
                'client_id' =>
                    'skeletor-test',

                'client_secret' =>
                    'secret-test',

                'redirect' =>
                    'https://skeletor.test/auth/callback',

                'base_url' =>
                    'https://mindefconnect.test',

                'realms' =>
                    'skeletor',
            ]
        );

        $session =
            creerSessionPourInscriptionMindef();

        get(
            PublicInscriptionPage::getUrl(
                [
                    'session' =>
                        $session->id,
                ],
                panel:
                    'fpsplanificationstage'
            )
        )->assertRedirect(
            route(
                'keycloak.login.redirect'
            )
        );
    }
);

it(
    'affiche le formulaire avec l identite du compte MindefConnect',
    function (): void {
        Queue::fake();

        $session =
            creerSessionPourInscriptionMindef();

        $compteMindef =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),
                ]);

        $grade =
            Grade::factory()
                ->create([
                    'libelle_court' =>
                        'GRTEST',

                    'libelle_long' =>
                        'Grade long de test',
                ]);

        $specialite =
            Specialite::factory()
                ->create([
                    'libelle_court' =>
                        'SPTEST',

                    'libelle_long' =>
                        'Spécialité longue de test',
                ]);

        $brevet =
            Brevet::factory()
                ->create([
                    'libelle_court' =>
                        'BRTEST',

                    'libelle_long' =>
                        'Brevet long de test',
                ]);

        Marin::factory()
            ->create([
                'user_id' =>
                    null,

                'nom' =>
                    $compteMindef->nom,

                'prenom' =>
                    $compteMindef->prenom,

                'email' =>
                    $compteMindef->email,

                'grade_id' =>
                    $grade->id,

                'specialite_id' =>
                    $specialite->id,

                'brevet_id' =>
                    $brevet->id,
            ]);

        actingAs(
            $compteMindef
        );

        $data =
            app(
                PublicInscriptionPageService::class
            )
                ->form(
                    $session,
                    $compteMindef
                );

        expect($data['identity']['grade'])
            ->toBe(
                $grade->libelle_court
            )
            ->and($data['identity']['specialite'])
            ->toBe(
                $specialite->libelle_court
            )
            ->and($data['identity']['brevet'])
            ->toBe(
                $brevet->libelle_court
            );

        get(
            PublicInscriptionPage::getUrl(
                [
                    'session' =>
                        $session->id,
                ],
                panel:
                    'fpsplanificationstage'
            )
        )
            ->assertSuccessful()
            ->assertSee(
                $compteMindef->email
            )
            ->assertSee(
                $grade->libelle_long
            )
            ->assertSee(
                $specialite->libelle_long
            )
            ->assertSee(
                $brevet->libelle_long
            )
            ->assertDontSee(
                'name="telephone"',
                false
            );
    }
);

it(
    'préremplit la page inscription avec les informations de l utilisateur connecté',
    function (): void {
        $session =
            creerSessionPourInscriptionMindef();

        $compteMindef =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),

                    'nom' =>
                        'DURAND',

                    'prenom' =>
                        'Alice',

                    'email' =>
                        'alice.durand@example.test',
                ]);

        $grade =
            Grade::factory()
                ->create([
                    'libelle_court' =>
                        'PMTEST',

                    'libelle_long' =>
                        'Premier maître de test',
                ]);

        $specialite =
            Specialite::factory()
                ->create([
                    'libelle_court' =>
                        'NAVTEST',

                    'libelle_long' =>
                        'Navigation de test',
                ]);

        $brevet =
            Brevet::factory()
                ->create([
                    'libelle_court' =>
                        'BRSUP',

                    'libelle_long' =>
                        'Brevet supérieur de test',
                ]);

        $unite =
            Unite::factory()
                ->create([
                    'libelle_court' =>
                        'UNITEST',

                    'libelle_long' =>
                        'Unité longue de test',
                ]);

        Marin::factory()
            ->create([
                'user_id' =>
                    $compteMindef->id,

                'nom' =>
                    'NOM FICHE RH',

                'prenom' =>
                    'Prénom fiche RH',

                'email' =>
                    'fiche.rh@example.test',

                'matricule' =>
                    'MAT-12345',

                'nid' =>
                    'NID-12345',

                'grade_id' =>
                    $grade->id,

                'specialite_id' =>
                    $specialite->id,

                'brevet_id' =>
                    $brevet->id,

                'unite_id' =>
                    $unite->id,
            ]);

        actingAs(
            $compteMindef
        );

        $identity = app(
            PublicInscriptionPageService::class
        )->form(
            $session,
            $compteMindef
        )['identity'];

        expect($identity)
            ->toMatchArray([
                'nom' =>
                    'DURAND',

                'prenom' =>
                    'Alice',

                'email' =>
                    $compteMindef->email,

                'matricule' =>
                    'MAT-12345',

                'nid' =>
                    'NID-12345',

                'grade' =>
                    $grade->libelle_court,

                'specialite' =>
                    $specialite->libelle_court,

                'brevet' =>
                    $brevet->libelle_court,

                'unite' =>
                    $unite->libelle_court,
            ]);

        $response = get(
            PublicInscriptionPage::getUrl(
                [
                    'session' =>
                        $session->id,
                ],
                panel:
                    'fpsplanificationstage'
            )
        )
            ->assertSuccessful()
            ->assertSee(
                'name="nom"',
                false
            );

        $html =
            $response->getContent();

        $inputAttributes =
            function (
                string $name
            ) use ($html): ?string {
                preg_match(
                    '~<input\\b[^>]*\\bname="'
                    . preg_quote(
                        $name,
                        '~'
                    )
                    . '"[^>]*>~i',
                    $html,
                    $matches
                );

                return $matches[0]
                    ?? null;
            };

        $selectedOptionAttributes =
            function (
                string $name,
                string $value
            ) use ($html): ?string {
                preg_match(
                    '~<select\\b[^>]*\\bname="'
                    . preg_quote(
                        $name,
                        '~'
                    )
                    . '"[^>]*>(.*?)</select>~is',
                    $html,
                    $selectMatches
                );

                preg_match(
                    '~<option\\b'
                    . '(?=[^>]*\\bvalue="'
                    . preg_quote(
                        $value,
                        '~'
                    )
                    . '")'
                    . '(?=[^>]*\\bselected\\b)'
                    . '[^>]*>~i',
                    $selectMatches[1]
                        ?? '',
                    $optionMatches
                );

                return $optionMatches[0]
                    ?? null;
            };

        expect(
            $inputAttributes('nom')
        )
            ->toMatch(
                '~value="DURAND"~'
            )
            ->toMatch('~readonly~')
            ->and(
                $inputAttributes('prenom')
            )
            ->toMatch(
                '~value="Alice"~'
            )
            ->toMatch('~readonly~')
            ->and(
                $inputAttributes('email')
            )
            ->toMatch(
                '~value="'
                . preg_quote(
                    $compteMindef->email,
                    '~'
                )
                . '"~'
            )
            ->toMatch('~readonly~')
            ->and(
                $inputAttributes('matricule')
            )
            ->toMatch(
                '~value="MAT-12345"~'
            )
            ->and(
                $inputAttributes('nid')
            )
            ->toMatch(
                '~value="NID-12345"~'
            )
            ->and(
                $inputAttributes('unite')
            )
            ->toMatch(
                '~value="'
                . preg_quote(
                    $unite->libelle_court,
                    '~'
                )
                . '"~'
            )
            ->and(
                $selectedOptionAttributes(
                    'grade',
                    $grade->libelle_court
                )
            )
            ->not->toBeNull()
            ->and(
                $selectedOptionAttributes(
                    'specialite',
                    $specialite->libelle_court
                )
            )
            ->not->toBeNull()
            ->and(
                $selectedOptionAttributes(
                    'brevet',
                    $brevet->libelle_court
                )
            )
            ->not->toBeNull();
    }
);

it(
    'conserve l inscription MindefConnect sans creer automatiquement une fiche RH',
    function (): void {
        $session =
            creerSessionPourInscriptionMindef();

        $candidat =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),

                    'nom' =>
                        'DURAND',

                    'prenom' =>
                        'Alice',

                    'email' =>
                        'alice.'
                        . Str::lower(
                            Str::random(6)
                        )
                        . '@example.test',
                ]);

        $grade =
            Grade::factory()
                ->create();

        $specialite =
            Specialite::factory()
                ->create();

        $brevet =
            Brevet::factory()
                ->create();

        actingAs(
            $candidat
        );

        post(
            route(
                'fpsplanificationstage.public.inscription.store',
                [
                    'session' =>
                        $session->id,
                ]
            ),
            [
                'nom' =>
                    'Identité modifiée',

                'prenom' =>
                    'Non autorisée',

                'email' =>
                    'usurpation@example.test',

                'nid' =>
                    'NID-MINDEF',

                'matricule' =>
                    'MAT-MINDEF',

                'grade' =>
                    $grade->libelle_court,

                'specialite' =>
                    $specialite->libelle_court,

                'brevet' =>
                    $brevet->libelle_court,

                'telephone' =>
                    '01 02 03 04 05',

                'unite' =>
                    'Unité test',
            ]
        )
            ->assertRedirect(
                PlanningFormations::getUrl(
                    panel:
                        'fpsplanificationstage'
                )
            );

        $inscription =
            Inscription::query()
                ->where(
                    'session_stage_id',
                    $session->id
                )
                ->firstOrFail();

        expect($inscription->stagiaire_id)
            ->toBeNull()
            ->and($inscription->candidat_user_id)
            ->toBe($candidat->id)
            ->and($inscription->candidat_nom)
            ->toBe('DURAND')
            ->and($inscription->candidat_prenom)
            ->toBe('Alice')
            ->and($inscription->candidat_email)
            ->toBe($candidat->email)
            ->and($inscription->candidat_grade)
            ->toBe($grade->libelle_court)
            ->and($inscription->candidat_specialite)
            ->toBe($specialite->libelle_court)
            ->and($inscription->candidat_brevet)
            ->toBe($brevet->libelle_court)
            ->and($inscription->candidat_telephone)
            ->toBeNull()
            ->and($inscription->nom_complet)
            ->toBe('DURAND Alice');

        expect(
            Marin::withoutGlobalScopes()
                ->where(
                    'email',
                    $candidat->email
                )
                ->exists()
        )->toBeFalse();
    }
);

it(
    'refuse les références RH inconnues à l inscription',
    function (): void {
        $session =
            creerSessionPourInscriptionMindef();

        $candidat =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),
                ]);

        actingAs(
            $candidat
        );

        post(
            route(
                'fpsplanificationstage.public.inscription.store',
                [
                    'session' =>
                        $session->id,
                ]
            ),
            [
                'grade' =>
                    'GRADE-INCONNU',

                'specialite' =>
                    'SPECIALITE-INCONNUE',

                'brevet' =>
                    'BREVET-INCONNU',

                'unite' =>
                    'Unité test',
            ]
        )->assertSessionHasErrors([
            'grade',
            'specialite',
            'brevet',
        ]);

        expect(
            Inscription::query()
                ->where(
                    'session_stage_id',
                    $session->id
                )
                ->exists()
        )->toBeFalse();
    }
);

it(
    'reserve le bouton de creation RH aux utilisateurs autorises et rattache le marin',
    function (): void {
        Queue::fake();

        $session =
            creerSessionPourInscriptionMindef();

        $candidat =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),
                ]);

        $inscription =
            Inscription::create([
                'session_stage_id' =>
                    $session->id,

                'candidat_user_id' =>
                    $candidat->id,

                'candidat_nom' =>
                    'MARTIN',

                'candidat_prenom' =>
                    'Louise',

                'candidat_email' =>
                    'louise.'
                    . Str::lower(
                        Str::random(6)
                    )
                    . '@example.test',

                'candidat_nid' =>
                    'NID-CREATION',

                'candidat_matricule' =>
                    'MAT-CREATION',

                'candidat_unite' =>
                    'Unité test',

                'statut' =>
                    'attente_nemo',

                'source' =>
                    'public',
            ]);

        $sansPermission =
            User::factory()
                ->create();

        actingAs(
            $sansPermission
        );

        livewire(
            ListInscriptions::class
        )->assertActionHidden(
            TestAction::make(
                'creerMarin'
            )->table(
                $inscription
            )
        );

        expect(
            fn () => app(
                MarinDepuisInscriptionService::class
            )->creer(
                $inscription,
                [
                    'nom' =>
                        'MARTIN',

                    'prenom' =>
                        'Louise',

                    'email' =>
                        $inscription
                            ->candidat_email,
                ]
            )
        )->toThrow(
            AuthorizationException::class
        );

        $gestionnaire =
            User::factory()
                ->create();

        $permission =
            Permission::firstOrCreate([
                'name' =>
                    'rh::marins.create',

                'guard_name' =>
                    'web',
            ]);

        $gestionnaire->givePermissionTo(
            $permission
        );

        actingAs(
            $gestionnaire
        );

        livewire(
            ListInscriptions::class
        )
            ->assertActionVisible(
                TestAction::make(
                    'creerMarin'
                )->table(
                    $inscription
                )
            )
            ->callAction(
                TestAction::make(
                    'creerMarin'
                )->table(
                    $inscription
                ),
                [
                    'nom' =>
                        'MARTIN',

                    'prenom' =>
                        'Louise',

                    'email' =>
                        $inscription
                            ->candidat_email,

                    'nid' =>
                        'NID-CREATION',

                    'matricule' =>
                        'MAT-CREATION',
                ]
            )
            ->assertHasNoActionErrors();

        $inscription->refresh();

        $marin =
            Marin::withoutGlobalScopes()
                ->findOrFail(
                    $inscription
                        ->stagiaire_id
                );

        expect($marin->nom)
            ->toBe('MARTIN')
            ->and($marin->prenom)
            ->toBe('Louise')
            ->and($marin->user_id)
            ->toBe($candidat->id)
            ->and($marin->email)
            ->toBe(
                $inscription
                    ->candidat_email
            );
    }
);
