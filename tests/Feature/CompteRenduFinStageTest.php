<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Filament\Pages\SessionInstructeurDetail;
use Modules\FPSplanificationstage\Filament\Widgets\SessionsInstructeurTable;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\Marin;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\CompteRenduFinStageService;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('fpsplanificationstage'));
    $this->user = User::factory()->create();
    $this->formateur = Marin::withoutEvents(fn () => Marin::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(), 'nom' => 'FORMATEUR', 'prenom' => 'Alice',
        'nid' => 'CR-' . Str::random(8), 'matricule' => 'CR-' . Str::random(8),
        'user_id' => $this->user->getKey(),
    ]));
    $stage = Stage::create(['libelle_court' => 'EXA-TC', 'actif' => true]);
    $stage->instructeurs()->attach($this->formateur, ['actif' => true]);
    $this->session = SessionStage::create([
        'stage_id' => $stage->getKey(), 'debut' => now()->subDays(10),
        'fin' => now()->subDay(), 'statut' => 'terminee',
    ]);
    $this->session->instructeurs()->attach($this->formateur);
    $this->inscription = Inscription::create([
        'session_stage_id' => $this->session->getKey(),
        'candidat_nom' => 'DURAND', 'candidat_prenom' => 'Paul',
        'candidat_grade' => 'PM', 'candidat_matricule' => 'MAT-CR-001',
        'candidat_unite' => 'Unité test', 'nemo_recu' => true,
    ]);
    $this->service = app(CompteRenduFinStageService::class);
    actingAs($this->user);
});

it('enregistre la note et valide automatiquement au seuil et au dessus', function (float $note): void {
    $this->service->enregistrer($this->session->id, $this->inscription->id, [
        'note_fin_stage' => $note, 'stage_valide' => false,
        'date_attribution' => today()->toDateString(),
    ]);
    $inscription = $this->inscription->fresh();
    expect((float) $inscription->note_fin_stage)->toBe($note)
        ->and($inscription->stage_valide)->toBeTrue()
        ->and($this->service->resultat($inscription))->toBe('Validé')
        ->and($inscription->statut)->toBe('confirmee');
})->with([12.0, 16.25, 20.0]);

it('permet une decision manuelle sous le seuil et montre la note', function (bool $decision): void {
    $this->service->enregistrer($this->session->id, $this->inscription->id, [
        'note_fin_stage' => 11.5, 'stage_valide' => $decision,
        'date_attribution' => today()->toDateString(),
        'observations_fin_stage' => 'Décision du formateur',
    ]);
    $inscription = $this->inscription->fresh();
    expect($inscription->stage_valide)->toBe($decision)
        ->and($this->service->resultat($inscription))
        ->toBe('11,5/20 — ' . ($decision ? 'Validé' : 'Non validé'))
        ->and($inscription->observations_fin_stage)->toBe('Décision du formateur');
})->with([true, false]);

it('rejette les notes hors bareme et une decision manquante sous le seuil', function (array $data): void {
    expect(fn () => $this->service->enregistrer($this->session->id, $this->inscription->id, [
        ...$data, 'date_attribution' => today()->toDateString(),
    ]))->toThrow(ValidationException::class);
    expect($this->inscription->fresh()->note_fin_stage)->toBeNull();
})->with([
    [['note_fin_stage' => -1, 'stage_valide' => false]],
    [['note_fin_stage' => 21]],
    [['note_fin_stage' => 11]],
    [['note_fin_stage' => 10.123, 'stage_valide' => true]],
]);

it('saisit et modifie un resultat depuis la fiche Filament', function (): void {
    livewire(SessionInstructeurDetail::class, ['session' => $this->session->id])
        ->callAction('resultat', [
            'note_fin_stage' => 11, 'stage_valide' => 0,
            'date_attribution' => today()->toDateString(),
            'observations_fin_stage' => 'À revoir',
        ], ['inscription' => $this->inscription->id])
        ->assertHasNoActionErrors()
        ->assertSee('11/20');
    expect($this->inscription->fresh()->stage_valide)->toBeFalse();

    livewire(SessionInstructeurDetail::class, ['session' => $this->session->id])
        ->callAction('resultat', [
            'note_fin_stage' => 16.25,
            'date_attribution' => today()->toDateString(),
        ], ['inscription' => $this->inscription->id])
        ->assertHasNoActionErrors();
    expect($this->inscription->fresh()->stage_valide)->toBeTrue();
});

it('valide le formulaire de note', function (): void {
    livewire(SessionInstructeurDetail::class, ['session' => $this->session->id])
        ->callAction('resultat', [
            'note_fin_stage' => 21, 'date_attribution' => today()->toDateString(),
        ], ['inscription' => $this->inscription->id])
        ->assertHasActionErrors(['note_fin_stage']);
});

it('masque les actions avant la fin et bloque aussi la saisie directe', function (): void {
    $this->session->update(['fin' => now()->addDay(), 'statut' => 'confirmee']);
    livewire(SessionInstructeurDetail::class, ['session' => $this->session->id])
        ->assertActionHidden('resultat')->assertActionHidden('compteRendu');
    expect(fn () => $this->service->inscription($this->session->id, $this->inscription->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('refuse une inscription etrangere et un formateur non affecte', function (): void {
    $autreSession = SessionStage::create([
        'stage_id' => $this->session->stage_id,
        'debut' => now()->subDays(10), 'fin' => now()->subDay(), 'statut' => 'terminee',
    ]);
    $autre = Inscription::create(['session_stage_id' => $autreSession->id, 'candidat_nom' => 'AUTRE']);
    expect(fn () => $this->service->inscription($this->session->id, $autre->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(fn () => $this->service->donneesPdf($autreSession->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('refuse un compte rendu incomplet et telecharge un PDF complet sans note de reussite', function (): void {
    expect(fn () => $this->service->render($this->session->id))->toThrow(ValidationException::class);
    $this->service->enregistrer($this->session->id, $this->inscription->id, [
        'note_fin_stage' => 16.25, 'date_attribution' => today()->toDateString(),
    ]);
    $data = $this->service->donneesPdf($this->session->id);
    $data['resultats'] = [$this->inscription->id => $this->service->resultat($this->inscription->fresh())];
    $html = view('fpsplanificationstage::public.compte-rendu-fin-stage-pdf', $data)->render();
    expect($html)->toContain('COMPTE RENDU DE FIN DE STAGE', 'DURAND Paul', 'MAT-CR-001', 'Validé')
        ->not->toContain('16.25', '16,25');
    expect($this->service->render($this->session->id))->toStartWith('%PDF-');
    livewire(SessionInstructeurDetail::class, ['session' => $this->session->id])
        ->callAction('compteRendu')
        ->assertFileDownloaded('compte-rendu-fin-stage-' . $this->session->id . '.pdf');
});

it('garde les sessions terminees accessibles dans espace instructeur', function (): void {
    livewire(SessionsInstructeurTable::class)->assertCanSeeTableRecords([$this->session]);
});

it('affiche la note et la decision sous le seuil dans le document', function (): void {
    $this->service->enregistrer($this->session->id, $this->inscription->id, [
        'note_fin_stage' => 9.75, 'stage_valide' => false,
        'date_attribution' => today()->toDateString(),
    ]);
    $data = $this->service->donneesPdf($this->session->id);
    $data['resultats'] = [$this->inscription->id => $this->service->resultat($this->inscription->fresh())];
    expect(view('fpsplanificationstage::public.compte-rendu-fin-stage-pdf', $data)->render())
        ->toContain('9,75/20 — Non validé');
});

it('ne permet pas de saisir un resultat pour une inscription annulee', function (): void {
    $this->inscription->update(['statut' => 'annulee']);
    expect(fn () => $this->service->inscription($this->session->id, $this->inscription->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('refuse une date attribution hors de la periode permise', function (string $date): void {
    expect(fn () => $this->service->enregistrer($this->session->id, $this->inscription->id, [
        'note_fin_stage' => 15,
        'date_attribution' => today()->modify($date)->toDateString(),
    ]))->toThrow(ValidationException::class);
})->with(['-2 days', '+1 day']);

it('refuse les resultats pour une session annulee', function (): void {
    $this->session->update(['statut' => 'annulee']);
    expect(fn () => $this->service->render($this->session->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
