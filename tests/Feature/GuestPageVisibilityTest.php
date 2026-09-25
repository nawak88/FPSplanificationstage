<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FPSplanificationstage\Filament\Pages\Admission;
use Modules\FPSplanificationstage\Filament\Pages\Dashboard;
use Modules\FPSplanificationstage\Filament\Pages\EspaceInstructeur;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Filament\Pages\ReservationsSalles;
use Modules\FPSplanificationstage\Filament\Pages\Statistiques;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\BesoinFormationResource;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\IndisponibiliteInstructeurResource;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\InscriptionResource;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\InstructeurResource;
use Modules\FPSplanificationstage\Filament\Resources\Salles\SalleResource;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\PlanningSessionStages;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Filament\Resources\Stages\StageResource;
use Modules\FPSplanificationstage\Filament\Resources\Stagiaires\StagiaireResource;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel(
            'fpsplanificationstage'
        )
    );
});

it('ne montre aux visiteurs que le planning des formations', function (): void {
    expect(
        PlanningFormations::canAccess()
    )->toBeTrue();

    foreach (
        [
            Dashboard::class,
            EspaceInstructeur::class,
            Statistiques::class,
            PlanningSessionStages::class,
            Admission::class,
            ReservationsSalles::class,
            BesoinFormationResource::class,
            IndisponibiliteInstructeurResource::class,
            InscriptionResource::class,
            InstructeurResource::class,
            SalleResource::class,
            SessionStageResource::class,
            StageResource::class,
            StagiaireResource::class,
        ]
        as $protectedComponent
    ) {
        expect(
            $protectedComponent::canAccess()
        )->toBeFalse(
            $protectedComponent
            . ' doit être masqué aux visiteurs.'
        );
    }

    get(
        PlanningFormations::getUrl(
            panel:
                'fpsplanificationstage'
        )
    )
        ->assertSuccessful()
        ->assertSee(
            'Planning des formations'
        )
        ->assertDontSee(
            'Statistiques'
        )
        ->assertDontSee(
            'Planning / Calendrier'
        )
        ->assertDontSee(
            'Catalogue des stages'
        )
        ->assertDontSee(
            'Admission'
        );
});

it('interdit aussi les accès directs aux pages de gestion', function (): void {
    get(
        Dashboard::getUrl(
            panel:
                'fpsplanificationstage'
        )
    )->assertForbidden();

    get(
        StageResource::getUrl(
            'index',
            panel:
                'fpsplanificationstage'
        )
    )->assertForbidden();

    get(
        SessionStageResource::getUrl(
            'planning',
            panel:
                'fpsplanificationstage'
        )
    )->assertForbidden();
});

it('laisse les pages de gestion accessibles après authentification', function (): void {
    actingAs(
        User::factory()
            ->create()
    );

    foreach (
        [
            Dashboard::class,
            Statistiques::class,
            PlanningSessionStages::class,
            Admission::class,
            ReservationsSalles::class,
        ]
        as $protectedPage
    ) {
        expect(
            $protectedPage::canAccess()
        )->toBeTrue(
            $protectedPage
            . ' doit être accessible après authentification.'
        );
    }
});
