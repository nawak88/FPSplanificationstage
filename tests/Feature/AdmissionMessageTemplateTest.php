<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FPSplanificationstage\Filament\Pages\Admission;
use Modules\FPSplanificationstage\Models\AdmissionMessageTemplate;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(
        Filament::getPanel('fpsplanificationstage')
    );

    actingAs(User::factory()->create());
});

it('permet de creer plusieurs modeles sans ecraser les precedents', function (): void {
    $component = livewire(Admission::class)
        ->set('templateName', 'Admission standard')
        ->set('templateStageId', 0)
        ->set('subjectTemplate', 'Admission {stage}')
        ->set(
            'bodyTemplate',
            "Admis :\n{admis}\n\nRefusés :\n{refuses}"
        )
        ->call('saveTemplate')
        ->assertHasNoErrors();

    $premierId = $component->get('templateId');

    $component
        ->call('newTemplate')
        ->assertSet('templateId', null)
        ->set('templateName', 'Admission avec hébergement')
        ->set(
            'bodyTemplate',
            "Hébergement prévu.\n\nAdmis :\n{admis}"
        )
        ->call('saveTemplate')
        ->assertHasNoErrors();

    expect($component->get('templateId'))
        ->not->toBe($premierId)
        ->and(
            AdmissionMessageTemplate::query()
                ->orderBy('id')
                ->pluck('nom')
                ->all()
        )
        ->toBe([
            'Admission standard',
            'Admission avec hébergement',
        ]);

    $this->assertDatabaseHas(
        'admission_message_templates',
        [
            'nom' => 'Admission standard',
            'stage_id' => null,
        ]
    );
});

it('charge automatiquement tout modele selectionne meme pour un autre stage', function (): void {
    $stageSelectionne = Stage::create([
        'libelle_court' => 'Stage sélectionné',
        'actif' => true,
    ]);

    $autreStage = Stage::create([
        'libelle_court' => 'Autre stage',
        'actif' => true,
    ]);

    $session = SessionStage::create([
        'stage_id' => $stageSelectionne->getKey(),
        'debut' => now()->addMonth(),
        'fin' => now()->addMonth()->addDay(),
        'statut' => 'planifiee',
    ]);

    $template = AdmissionMessageTemplate::create([
        'stage_id' => $autreStage->getKey(),
        'nom' => 'Modèle autre stage',
        'objet' => 'Objet personnalisé',
        'corps' => 'Corps personnalisé {stage}',
        'format_admis' => '{nom} {prenom}',
        'format_refuse' => '{nom}',
        'actif' => true,
    ]);

    $component = livewire(Admission::class)
        ->set('sessionId', $session->getKey());

    expect(
        $component
            ->instance()
            ->templateOptions()
    )->toHaveKey($template->getKey());

    $component
        ->set('templateId', $template->getKey())
        ->assertSet('templateName', 'Modèle autre stage')
        ->assertSet('subjectTemplate', 'Objet personnalisé')
        ->assertSet('bodyTemplate', 'Corps personnalisé {stage}')
        ->assertSet(
            'templateStageId',
            $autreStage->getKey()
        );
});

it('duplique un modele avant de sauvegarder sa variante', function (): void {
    $template = AdmissionMessageTemplate::create([
        'stage_id' => null,
        'nom' => 'Modèle initial',
        'objet' => 'Admission {stage}',
        'corps' => 'Liste : {admis}',
        'format_admis' => '{grade} {nom}',
        'format_refuse' => '{nom}',
        'actif' => true,
    ]);

    livewire(Admission::class)
        ->set('templateId', $template->getKey())
        ->call('duplicateTemplate')
        ->assertSet('templateId', null)
        ->assertSet(
            'templateName',
            'Copie de Modèle initial'
        )
        ->call('saveTemplate')
        ->assertHasNoErrors();

    expect(
        AdmissionMessageTemplate::query()->count()
    )->toBe(2);

    $this->assertDatabaseHas(
        'admission_message_templates',
        [
            'nom' => 'Copie de Modèle initial',
            'corps' => 'Liste : {admis}',
        ]
    );
});

it('explique les champs automatiques directement sur la page', function (): void {
    livewire(Admission::class)
        ->assertSee('Aide — champs automatiques')
        ->assertSee('Comment les utiliser ?')
        ->assertSee('libellé court du stage')
        ->assertSee('nom du candidat')
        ->assertSee('{admis}')
        ->assertSee('{matricule}');
});

it('refuse d enregistrer un modele sans nom', function (): void {
    livewire(Admission::class)
        ->set('templateName', '')
        ->call('saveTemplate')
        ->assertHasErrors([
            'templateName',
        ]);

    expect(
        AdmissionMessageTemplate::query()->count()
    )->toBe(0);
});
