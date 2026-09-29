<?php

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FPSplanificationstage\Filament\Pages\Admission;
use Modules\FPSplanificationstage\Models\AdmissionMessageTemplate;
use Modules\FPSplanificationstage\Models\Inscription;
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
    $component = livewire(Admission::class)
        ->assertActionExists(
            'automaticFieldsHelp',
            fn (Action $action): bool =>
                $action->isIconButton()
                && $action->getIcon() === 'heroicon-o-question-mark-circle'
                && $action->getModalHeading() === 'Champs automatiques'
                && $action->getModalSubmitAction() === null
        )
        ->mountAction('automaticFieldsHelp')
        ->assertActionMounted('automaticFieldsHelp');

    expect($component->instance()->mountedActionShouldOpenModal())
        ->toBeTrue()
        ->and($component->instance()->mountedActionHasSchema())
        ->toBeTrue()
        ->and($component->instance()->sessionFieldsHelpText())
        ->toContain('libellé court du stage', '{admis}')
        ->and($component->instance()->candidateFieldsHelpText())
        ->toContain('nom du candidat', '{matricule}');
});

it('ouvre un apercu du message genere avec les donnees de la session', function (): void {
    $stage = Stage::create([
        'libelle_court' => 'MENTOR',
        'libelle_long' => 'Certificat Mentor',
        'actif' => true,
    ]);

    $session = SessionStage::create([
        'stage_id' => $stage->getKey(),
        'debut' => '2026-10-05 08:00:00',
        'fin' => '2026-10-09 16:00:00',
        'statut' => 'planifiee',
    ]);

    $candidate = Inscription::create([
        'session_stage_id' => $session->getKey(),
        'candidat_nom' => 'DUPONT',
        'candidat_prenom' => 'Alice',
        'candidat_grade' => 'PM',
        'candidat_matricule' => '12345',
        'candidat_unite' => 'ACHERON',
        'statut' => 'confirmee',
        'nemo_recu' => true,
    ]);

    $template = AdmissionMessageTemplate::create([
        'nom' => 'Aperçu admission',
        'objet' => 'Admission {stage} — {session}',
        'corps' => "Du {date_debut} au {date_fin}\n\nAdmis :\n{admis}",
        'format_admis' => '- {grade} {nom} {prenom} — {matricule} — {unite}',
        'format_refuse' => '- {grade} {nom} {prenom}',
        'actif' => true,
    ]);

    $expectedSubject = 'Admission MENTOR — ' . $session->code_session;
    $expectedBody = "Du 05/10/2026 au 09/10/2026\n\n"
        . "Admis :\n- PM DUPONT Alice — 12345 — ACHERON";

    livewire(Admission::class)
        ->set('sessionId', $session->getKey())
        ->assertSet("candidateDecisions.{$candidate->getKey()}", 'admis')
        ->set('templateId', $template->getKey())
        ->mountAction('generateMessage')
        ->assertActionMounted('generateMessage')
        ->assertSet('finalSubject', $expectedSubject)
        ->assertSet('finalBody', $expectedBody)
        ->assertSet('generationWarning', '')
        ->assertActionDataSet([
            'previewSubject' => $expectedSubject,
            'previewBody' => $expectedBody,
        ]);
});

it('signale les champs candidat places directement dans le corps', function (): void {
    $stage = Stage::create([
        'libelle_court' => 'TEST',
        'actif' => true,
    ]);

    $session = SessionStage::create([
        'stage_id' => $stage->getKey(),
        'debut' => '2026-10-05 08:00:00',
        'fin' => '2026-10-05 16:00:00',
        'statut' => 'planifiee',
    ]);

    $component = livewire(Admission::class)
        ->set('sessionId', $session->getKey())
        ->set('bodyTemplate', 'Candidat : {grade} {nom} — {affectation}')
        ->mountAction('generateMessage')
        ->assertActionMounted('generateMessage');

    expect($component->get('generationWarning'))
        ->toContain('{grade}', '{nom}', '{affectation}')
        ->toContain('utilisez {admis} et {refuses}');
});

it('range automatiquement les admis sous alpha et les refuses sous bravo', function (): void {
    $stage = Stage::create([
        'libelle_court' => 'ESSAI',
        'actif' => true,
    ]);

    $session = SessionStage::create([
        'stage_id' => $stage->getKey(),
        'debut' => '2026-09-21 08:00:00',
        'fin' => '2026-09-25 16:00:00',
        'statut' => 'planifiee',
    ]);

    $admitted = Inscription::create([
        'session_stage_id' => $session->getKey(),
        'candidat_nom' => 'DUPONT',
        'candidat_prenom' => 'Alice',
        'candidat_grade' => 'PM',
        'candidat_specialite' => 'NAVIT',
        'candidat_matricule' => '11111',
        'candidat_unite' => 'ACHERON',
        'statut' => 'confirmee',
        'nemo_recu' => true,
    ]);

    $refused = Inscription::create([
        'session_stage_id' => $session->getKey(),
        'candidat_nom' => 'MARTIN',
        'candidat_prenom' => 'Bob',
        'candidat_grade' => 'QM1',
        'candidat_specialite' => 'MECAN',
        'candidat_matricule' => '22222',
        'candidat_unite' => 'FREGATE',
        'statut' => 'refusee',
        'nemo_recu' => false,
    ]);

    $body = <<<'TEXT'
ALPHA / Personnel admis :
- {grade} {specialite} {nom} {prenom} — {matricule} - {unite}

BRAVO / Personnel non retenu :
- {grade} {specialite} {nom} {prenom} — {matricule} - {unite}

SECUNDO / MODALITÉS
Accueil à 08h00.
TEXT;

    $component = livewire(Admission::class)
        ->set('sessionId', $session->getKey())
        ->assertSet("candidateDecisions.{$admitted->getKey()}", 'admis')
        ->assertSet("candidateDecisions.{$refused->getKey()}", 'refuse')
        ->set('bodyTemplate', $body)
        ->mountAction('generateMessage')
        ->assertActionMounted('generateMessage')
        ->assertSet('generationWarning', '');

    $generatedBody = $component->get('finalBody');
    $alpha = str($generatedBody)
        ->between('ALPHA / Personnel admis :', 'BRAVO / Personnel non retenu :')
        ->toString();
    $bravo = str($generatedBody)
        ->between('BRAVO / Personnel non retenu :', 'SECUNDO / MODALITÉS')
        ->toString();

    expect($alpha)
        ->toContain('PM NAVIT DUPONT Alice — 11111 - ACHERON')
        ->not->toContain('MARTIN')
        ->and($bravo)
        ->toContain('QM1 MECAN MARTIN Bob — 22222 - FREGATE')
        ->not->toContain('DUPONT');
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
