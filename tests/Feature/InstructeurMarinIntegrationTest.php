<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\InstructeurStageImporter;
use Modules\RH\Models\Marin;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

it('uses RH marins for every instructor relationship', function () {
    $marin = Marin::withoutEvents(
        fn (): Marin => Marin::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'nom' => 'DUPONT',
            'prenom' => 'Jeanne',
            'nid' => 'NID-INST-001',
            'matricule' => 'MAT-INST-001',
            'email' => 'jeanne.dupont@example.test',
        ])
    );

    $stage = Stage::create([
        'libelle_court' => 'Stage avec formateur RH',
        'actif' => true,
    ]);

    $session = SessionStage::create([
        'stage_id' => $stage->id,
        'debut' => '2026-10-01 09:00:00',
        'fin' => '2026-10-01 17:00:00',
        'statut' => 'planifiee',
    ]);

    $stage->instructeurs()->attach($marin);
    $session->instructeurs()->attach($marin);

    $indisponibilite = IndisponibiliteInstructeur::create([
        'instructeur_id' => $marin->id,
        'date_debut' => '2026-10-02',
        'date_fin' => '2026-10-02',
        'journee_entiere' => true,
        'actif' => true,
    ]);

    expect(Schema::hasTable('instructeurs'))->toBeFalse()
        ->and($stage->fresh()->instructeurs->sole())->toBeInstanceOf(Marin::class)
        ->and($session->fresh()->instructeurs->sole())->toBeInstanceOf(Marin::class)
        ->and($indisponibilite->fresh()->instructeur)->toBeInstanceOf(Marin::class)
        ->and($indisponibilite->instructeur->is($marin))->toBeTrue();

    $this->assertDatabaseHas('instructeur_stage', [
        'stage_id' => $stage->id,
        'instructeur_id' => $marin->id,
    ]);

    $this->assertDatabaseHas('instructeur_session_stage', [
        'session_stage_id' => $session->id,
        'instructeur_id' => $marin->id,
    ]);
});

it('cascades instructor assignments when the RH marin is deleted', function () {
    $marin = Marin::withoutEvents(
        fn (): Marin => Marin::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'nom' => 'MARTIN',
            'prenom' => 'Luc',
            'nid' => 'NID-INST-002',
            'matricule' => 'MAT-INST-002',
        ])
    );

    $stage = Stage::create([
        'libelle_court' => 'Stage supprimable',
        'actif' => true,
    ]);

    $stage->instructeurs()->attach($marin);

    IndisponibiliteInstructeur::create([
        'instructeur_id' => $marin->id,
        'date_debut' => '2026-10-03',
        'date_fin' => '2026-10-03',
        'journee_entiere' => true,
        'actif' => true,
    ]);

    Marin::withoutEvents(fn () => $marin->delete());

    $this->assertDatabaseMissing('instructeur_stage', [
        'instructeur_id' => $marin->id,
    ]);

    $this->assertDatabaseMissing('indisponibilite_instructeurs', [
        'instructeur_id' => $marin->id,
    ]);
});

it('migrates legacy instructors and their assignments to RH marins', function () {
    $migration = require module_path(
        'FPSplanificationstage',
        'database/migrations/2026_09_22_120000_fpsplanificationstage_replace_instructeurs_with_marins.php'
    );

    $migration->down();

    $stage = Stage::create([
        'libelle_court' => 'Stage historique',
        'actif' => true,
    ]);

    $legacyInstructorId = DB::table('instructeurs')->insertGetId([
        'identifiant_interne' => 'MAT-LEGACY-001',
        'nom' => 'LEMOINE',
        'prenom' => 'Alice',
        'email' => 'alice.lemoine@example.test',
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('instructeur_stage')->insert([
        'instructeur_id' => $legacyInstructorId,
        'stage_id' => $stage->id,
        'role' => 'principal',
        'actif' => true,
        'source' => 'manuel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    $marin = Marin::withoutGlobalScopes()
        ->where('matricule', 'MAT-LEGACY-001')
        ->sole();

    expect(Schema::hasTable('instructeurs'))->toBeFalse()
        ->and($marin->nom)->toBe('LEMOINE')
        ->and($stage->fresh()->instructeurs->sole()->is($marin))->toBeTrue();

    $this->assertDatabaseHas('instructeur_stage', [
        'stage_id' => $stage->id,
        'instructeur_id' => $marin->id,
        'role' => 'principal',
    ]);
});

it('imports instructors as RH marins and associates them with stages', function () {
    Queue::fake();

    $stage = Stage::create([
        'code_stage' => 'STG-IMPORT-MARIN',
        'libelle_court' => 'Stage import marin',
        'actif' => true,
    ]);

    $spreadsheet = new Spreadsheet();

    $spreadsheet->getActiveSheet()
        ->setTitle('Formateurs')
        ->fromArray([
            ['Nom', 'Prénom', 'Identifiant interne', 'Email'],
            ['BERNARD', 'Émilie', 'MAT-IMPORT-001', 'emilie.bernard@example.test'],
        ]);

    $spreadsheet->createSheet()
        ->setTitle('Stages délivrés')
        ->fromArray([
            ['Identifiant formateur', 'Code stage', 'Rôle', 'Actif'],
            ['MAT-IMPORT-001', 'STG-IMPORT-MARIN', 'Principal', 'Oui'],
        ]);

    $path = tempnam(sys_get_temp_dir(), 'fps-instructeurs-');

    try {
        (new Xlsx($spreadsheet))->save($path);

        $result = app(InstructeurStageImporter::class)
            ->import($path);
    } finally {
        if (is_string($path) && file_exists($path)) {
            unlink($path);
        }
    }

    $marin = Marin::withoutGlobalScopes()
        ->where('matricule', 'MAT-IMPORT-001')
        ->sole();

    expect($result)
        ->toMatchArray([
            'instructeurs_crees' => 1,
            'associations_creees' => 1,
            'erreurs' => [],
        ])
        ->and($marin->email)->toBe('emilie.bernard@example.test')
        ->and($stage->fresh()->instructeurs->sole()->is($marin))->toBeTrue();
});
