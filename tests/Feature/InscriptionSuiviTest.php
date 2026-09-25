<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages\EditInscription;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel(
            'fpsplanificationstage'
        )
    );

    actingAs(
        User::factory()
            ->create()
    );
});

function creerInscriptionPourLeSuivi(
    array $attributs = []
): Inscription {
    $suffixe =
        Str::lower(
            Str::random(8)
        );

    $marin = Marin::withoutEvents(
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

    $stage = Stage::create([
        'code_stage' =>
            'STG-' . $suffixe,

        'libelle_court' =>
            'Stage suivi inscription',

        'actif' =>
            true,
    ]);

    $session = SessionStage::create([
        'stage_id' =>
            $stage->id,

        'debut' =>
            '2026-10-10 09:00:00',

        'fin' =>
            '2026-10-10 17:00:00',

        'capacite_max' =>
            10,

        'statut' =>
            'planifiee',
    ]);

    return Inscription::create(
        array_merge(
            [
                'session_stage_id' =>
                    $session->id,

                'stagiaire_id' =>
                    $marin->id,

                'statut' =>
                    null,

                'nemo_recu' =>
                    false,

                'derogation_demandee' =>
                    false,

                'source' =>
                    'manuel',
            ],
            $attributs
        )
    );
}

it(
    'pilote le statut normal avec la coche NEMO',
    function (): void {
        $inscription =
            creerInscriptionPourLeSuivi();

        expect($inscription->statut)
            ->toBe('attente_nemo');

        livewire(
            EditInscription::class,
            [
                'record' =>
                    $inscription->getRouteKey(),
            ]
        )
            ->assertSchemaComponentExists(
                'statut',
                checkComponentUsing:
                    fn (Select $component): bool =>
                        ! array_key_exists(
                            'attente_nemo',
                            $component->getOptions()
                        )
                        && ! array_key_exists(
                            'confirmee',
                            $component->getOptions()
                        )
            )
            ->assertSchemaComponentStateSet(
                'statut',
                null
            )
            ->assertSchemaComponentVisible(
                'statut'
            )
            ->fillForm([
                'nemo_recu' =>
                    true,

                'statut' =>
                    null,
            ])
            ->assertSchemaComponentHidden(
                'statut'
            )
            ->call('save')
            ->assertHasNoFormErrors();

        $inscription->refresh();

        expect($inscription->statut)
            ->toBe('confirmee')
            ->and($inscription->nemo_recu)
            ->toBeTrue()
            ->and($inscription->nemo_recu_at)
            ->not->toBeNull();
    }
);

it(
    'conserve une situation particulière sélectionnée',
    function (): void {
        $inscription =
            creerInscriptionPourLeSuivi();

        $inscription->update([
            'nemo_recu' =>
                true,

            'statut' =>
                'annulee',
        ]);

        expect($inscription->fresh()->statut)
            ->toBe('annulee');
    }
);

it(
    'permet de joindre le document lors de l acceptation de la dérogation',
    function (): void {
        Storage::fake('local');

        $inscription =
            creerInscriptionPourLeSuivi([
                'statut' =>
                    'attente_derogation',

                'derogation_demandee' =>
                    true,

                'derogation_statut' =>
                    'en_attente',
            ]);

        $document =
            UploadedFile::fake()
                ->create(
                    'derogation-acceptee.pdf',
                    100,
                    'application/pdf'
                );

        $page = livewire(
            EditInscription::class,
            [
                'record' =>
                    $inscription->getRouteKey(),
            ]
        )
            ->assertActionVisible(
                'accepterDerogation'
            )
            ->assertSchemaComponentHidden(
                'derogation_document'
            )
            ->callAction(
                'accepterDerogation',
                [
                    'derogation_document' =>
                        $document,
                ]
            )
            ->assertHasNoFormErrors()
            ->assertActionHidden(
                'accepterDerogation'
            )
            ->assertActionVisible(
                'telechargerDerogation'
            )
            ->assertSchemaComponentVisible(
                'derogation_document'
            );

        $inscription->refresh();

        expect($inscription->derogation_statut)
            ->toBe('acceptee')
            ->and($inscription->statut)
            ->toBe('attente_nemo')
            ->and($inscription->derogation_document)
            ->toBeString();

        Storage::disk('local')->assertExists(
            $inscription->derogation_document
        );

        $page
            ->callAction(
                'telechargerDerogation'
            )
            ->assertFileDownloaded(
                'derogation-'
                . $inscription
                    ->code_inscription
                . '.pdf'
            );
    }
);

it(
    'refuse un document de dérogation non autorisé',
    function (): void {
        Storage::fake('local');

        $inscription =
            creerInscriptionPourLeSuivi([
                'statut' =>
                    'attente_derogation',

                'derogation_demandee' =>
                    true,

                'derogation_statut' =>
                    'en_attente',
            ]);

        livewire(
            EditInscription::class,
            [
                'record' =>
                    $inscription->getRouteKey(),
            ]
        )
            ->callAction(
                'accepterDerogation',
                [
                    'derogation_document' =>
                        UploadedFile::fake()
                            ->create(
                                'derogation.txt',
                                10,
                                'text/plain'
                            ),
                ]
            )
            ->assertHasFormErrors([
                'derogation_document',
            ]);

        $inscription->refresh();

        expect($inscription->derogation_statut)
            ->toBe('en_attente')
            ->and($inscription->derogation_document)
            ->toBeNull();
    }
);
