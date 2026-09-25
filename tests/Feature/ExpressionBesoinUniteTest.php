<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinNouveau;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\PublicBesoinFormationPageService;
use Modules\RH\Models\Marin;
use Modules\RH\Models\Unite;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Queue::fake();

    Filament::setCurrentPanel(
        Filament::getPanel(
            'fpsplanificationstage'
        )
    );
});

it(
    'propose les libellés longs et présélectionne l unité du marin MindefConnect',
    function (): void {
        $unite =
            Unite::factory()
                ->create([
                    'libelle_court' =>
                        'LANGLADE',

                    'libelle_long' =>
                        'Base navale de Langlade',
                ]);

        $compteMindef =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),
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

                'unite_id' =>
                    $unite->id,
            ]);

        actingAs(
            $compteMindef
        );

        $data =
            app(
                PublicBesoinFormationPageService::class
            )
                ->form(
                    $compteMindef
                );

        expect($data['demandeur'])
            ->toBe(
                $unite->libelle_long
            )
            ->and($data['unites'])
            ->toContain(
                $unite->libelle_long
            );

        get(
            BesoinNouveau::getUrl(
                panel:
                    'fpsplanificationstage'
            )
        )
            ->assertSuccessful()
            ->assertSee(
                'list="unites-demandeur"',
                false
            )
            ->assertSee(
                $unite->libelle_long
            );
    }
);

it(
    'retrouve l unité transmise par MindefConnect avec le libannudef',
    function (): void {
        $unite =
            Unite::factory()
                ->create([
                    'libelle_court' =>
                        'FPS TEST',

                    'libelle_long' =>
                        'Formation professionnelle de test',

                    'libannudef' =>
                        'MARINE/FORMATION/TEST',
                ]);

        $compteMindef =
            User::factory()
                ->create([
                    'sub' =>
                        'mindef-' . Str::uuid(),
                ]);

        $compteMindef->storeMindefConnectInformations([
            'main_department_number' =>
                $unite->libannudef,
        ]);

        actingAs(
            $compteMindef
        );

        expect(
            app(
                PublicBesoinFormationPageService::class
            )
                ->form(
                    $compteMindef
                )['demandeur']
        )->toBe(
            $unite->libelle_long
        );
    }
);

it(
    'enregistre uniquement une unité du référentiel RH',
    function (): void {
        $stage = Stage::create([
            'code_stage' =>
                'STG-BESOIN-UNITE',

            'libelle_court' =>
                'Stage besoin unité',

            'duree_jours' =>
                1,

            'actif' =>
                true,
        ]);

        $unite =
            Unite::factory()
                ->create([
                    'libelle_long' =>
                        'Unité reconnue pour le besoin',
                ]);

        $date = now()
            ->addMonth()
            ->startOfWeek()
            ->addWeek()
            ->format('Y-m-d');

        $payload = [
            'demandeur' =>
                $unite->libelle_long,

            'contact_nom' =>
                'Contact test',

            'contact_email' =>
                'contact@example.test',

            'besoins' => [[
                'stage_id' =>
                    $stage->id,

                'type_periode' =>
                    'dates_fixes',

                'date_debut_souhaitee' =>
                    $date,

                'nombre_stagiaires' =>
                    1,
            ]],
        ];

        post(
            route(
                'fpsplanificationstage.public.besoin.store'
            ),
            $payload
        )->assertRedirect();

        expect(
            BesoinFormation::query()
                ->where(
                    'demandeur',
                    $unite->libelle_long
                )
                ->exists()
        )->toBeTrue();

        $payload['demandeur'] =
            'Unité absente du référentiel';

        post(
            route(
                'fpsplanificationstage.public.besoin.store'
            ),
            $payload
        )
            ->assertSessionHasErrors(
                'demandeur'
            );

        expect(
            BesoinFormation::query()
                ->where(
                    'demandeur',
                    'Unité absente du référentiel'
                )
                ->exists()
        )->toBeFalse();
    }
);
