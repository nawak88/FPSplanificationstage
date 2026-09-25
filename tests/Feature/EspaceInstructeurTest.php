<?php

use App\Models\User;
use Filament\Facades\Filament;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Pages\EspaceInstructeur;
use Modules\FPSplanificationstage\Filament\Widgets\CalendrierInstructeur;
use Modules\FPSplanificationstage\Filament\Widgets\SessionsInstructeurTable;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;

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

function creerMarinInstructeur(
    User $user,
    string $suffixe = '001'
): Marin {
    return Marin::withoutEvents(
        fn (): Marin => Marin::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'nom' => 'INSTRUCTEUR',
            'prenom' => 'Marine',
            'nid' => 'NID-ESPACE-' . $suffixe,
            'matricule' => 'MAT-ESPACE-' . $suffixe,
            'email' => 'instructeur-' . $suffixe . '@example.test',
            'user_id' => $user->getKey(),
        ])
    );
}

function creerSessionInstructeur(
    Marin $instructeur,
    string $libelle = 'Formation instructeur',
    int $jours = 10
): SessionStage {
    $stage = Stage::create([
        'libelle_court' => $libelle,
        'actif' => true,
    ]);

    $stage->instructeurs()->attach(
        $instructeur,
        ['actif' => true]
    );

    $session = SessionStage::create([
        'stage_id' => $stage->getKey(),
        'debut' => now()
            ->addDays($jours)
            ->setTime(8, 0),
        'fin' => now()
            ->addDays($jours)
            ->setTime(16, 0),
        'statut' => 'planifiee',
    ]);

    $session->instructeurs()->attach(
        $instructeur
    );

    return $session;
}

it(
    'ouvre l espace instructeur au marin authentifie meme sans affectation',
    function (): void {
        expect(
            EspaceInstructeur::canAccess()
        )->toBeFalse();

        $userSansFiche = User::factory()->create();

        actingAs($userSansFiche);

        expect(
            EspaceInstructeur::canAccess()
        )->toBeFalse();

        $user = User::factory()->create();
        creerMarinInstructeur($user);

        actingAs($user);

        expect(
            EspaceInstructeur::canAccess()
        )->toBeTrue();

        get(
            EspaceInstructeur::getUrl(
                panel: 'fpsplanificationstage'
            )
        )
            ->assertSuccessful()
            ->assertSee('Mon espace instructeur');
    }
);

it(
    'reconnait une fiche marin MindefConnect non encore reliee par user id',
    function (): void {
        $user = User::factory()->create([
            'nom' => 'RECONNU',
            'prenom' => 'Claire',
            'email' => 'claire.reconnue@example.test',
        ]);

        Marin::withoutEvents(
            fn (): Marin => Marin::withoutGlobalScopes()->create([
                'uuid' => (string) Str::uuid(),
                'nom' => 'RECONNU',
                'prenom' => 'Claire',
                'nid' => 'NID-RECONNU',
                'matricule' => 'MAT-RECONNU',
                'email' => 'claire.reconnue@example.test',
            ])
        );

        actingAs($user);

        expect(
            EspaceInstructeur::canAccess()
        )->toBeTrue();
    }
);

it(
    'enregistre depuis le calendrier uniquement l indisponibilite du marin connecte et alerte le gestionnaire',
    function (): void {
        $gestionnaire = User::factory()->create([
            'admin' => true,
        ]);

        $user = User::factory()->create();
        $instructeur = creerMarinInstructeur(
            $user,
            '002'
        );

        $session = creerSessionInstructeur(
            $instructeur,
            'Formation en conflit',
            12
        );

        actingAs($user);

        $date = $session->debut->toDateString();

        livewire(CalendrierInstructeur::class)
            ->call(
                'onDateSelectJs',
                [
                    'start' => $date . 'T00:00:00+00:00',
                    'end' => $session->debut
                        ->copy()
                        ->addDay()
                        ->toDateString()
                        . 'T00:00:00+00:00',
                    'allDay' => true,
                    'tzOffset' => 0,
                    'view' => [
                        'type' => 'dayGridMonth',
                        'title' => 'Calendrier',
                        'currentStart' =>
                            $date . 'T00:00:00+00:00',
                        'currentEnd' => $session->debut
                            ->copy()
                            ->addMonth()
                            ->toDateString()
                            . 'T00:00:00+00:00',
                        'activeStart' =>
                            $date . 'T00:00:00+00:00',
                        'activeEnd' => $session->debut
                            ->copy()
                            ->addMonth()
                            ->toDateString()
                            . 'T00:00:00+00:00',
                    ],
                ]
            )
            ->assertSet(
                'selectionDateDebut',
                $date
            )
            ->assertSet(
                'selectionDateFin',
                $date
            )
            ->assertActionMounted(
                'declarerIndisponibilite'
            );

        livewire(CalendrierInstructeur::class)
            ->callAction(
                'declarerIndisponibilite',
                [
                    'indisponible' => true,
                    'journee_entiere' => true,
                    'date_debut' => $date,
                    'date_fin' => $date,
                    'motif' => 'mission',
                    'commentaire' =>
                        'Mission extérieure',
                ]
            )
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(
            'indisponibilite_instructeurs',
            [
                'instructeur_id' =>
                    $instructeur->getKey(),
                'date_debut' => $date,
                'date_fin' => $date,
                'motif' => 'mission',
                'actif' => true,
            ]
        );

        $notification = $gestionnaire
            ->notifications()
            ->sole();

        expect($notification->data['title'])
            ->toBe(
                'Nouvelle indisponibilité instructeur'
            )
            ->and($notification->data['body'])
            ->toContain('Formation en conflit')
            ->toContain($session->code_session);
    }
);

it(
    'ne montre dans le calendrier que les sessions et indisponibilites de l instructeur connecte',
    function (): void {
        $user = User::factory()->create();
        $instructeur = creerMarinInstructeur(
            $user,
            '003'
        );

        $autreUser = User::factory()->create();
        $autreInstructeur = creerMarinInstructeur(
            $autreUser,
            '004'
        );

        $session = creerSessionInstructeur(
            $instructeur,
            'Ma formation',
            14
        );

        creerSessionInstructeur(
            $autreInstructeur,
            'Formation étrangère',
            14
        );

        IndisponibiliteInstructeur::create([
            'instructeur_id' => $instructeur->getKey(),
            'date_debut' => $session->debut->toDateString(),
            'date_fin' => $session->debut->toDateString(),
            'journee_entiere' => true,
            'motif' => 'conge',
            'actif' => true,
        ]);

        IndisponibiliteInstructeur::create([
            'instructeur_id' => $autreInstructeur->getKey(),
            'date_debut' => $session->debut->toDateString(),
            'date_fin' => $session->debut->toDateString(),
            'journee_entiere' => true,
            'motif' => 'absence',
            'actif' => true,
        ]);

        actingAs($user);

        $widget = new CalendrierInstructeur();
        $events = (new ReflectionMethod(
            $widget,
            'getEvents'
        ))->invoke(
            $widget,
            new FetchInfo([
                'startStr' => now()
                    ->addDays(13)
                    ->startOfDay()
                    ->toIso8601String(),
                'endStr' => now()
                    ->addDays(15)
                    ->endOfDay()
                    ->toIso8601String(),
            ])
        );

        $titres = collect($events)
            ->map(
                fn ($event): string =>
                    (string) $event->getTitle()
            );

        expect($titres)
            ->toHaveCount(2)
            ->toContain('Stage — Ma formation')
            ->toContain('Indisponible — Congé')
            ->not->toContain(
                'Stage — Formation étrangère'
            )
            ->not->toContain(
                'Indisponible — Absence'
            );
    }
);

it(
    'affiche les stagiaires inscrits uniquement sur les sessions de l instructeur',
    function (): void {
        $user = User::factory()->create();
        $instructeur = creerMarinInstructeur(
            $user,
            '005'
        );

        $autreUser = User::factory()->create();
        $autreInstructeur = creerMarinInstructeur(
            $autreUser,
            '006'
        );

        $session = creerSessionInstructeur(
            $instructeur,
            'Formation visible',
            16
        );

        $autreSession = creerSessionInstructeur(
            $autreInstructeur,
            'Formation masquée',
            16
        );

        Inscription::create([
            'session_stage_id' => $session->getKey(),
            'candidat_nom' => 'DURAND',
            'candidat_prenom' => 'Alice',
            'candidat_email' => 'alice@example.test',
            'statut' => 'confirmee',
            'nemo_recu' => true,
        ]);

        Inscription::create([
            'session_stage_id' => $autreSession->getKey(),
            'candidat_nom' => 'MASQUE',
            'candidat_prenom' => 'Paul',
            'candidat_email' => 'paul@example.test',
            'statut' => 'confirmee',
            'nemo_recu' => true,
        ]);

        actingAs($user);

        livewire(SessionsInstructeurTable::class)
            ->assertCanSeeTableRecords([$session])
            ->assertCanNotSeeTableRecords([
                $autreSession,
            ])
            ->assertSee('DURAND Alice')
            ->assertDontSee('MASQUE Paul');
    }
);
