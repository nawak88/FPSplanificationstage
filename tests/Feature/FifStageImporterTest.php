<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\FifStageImporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('FPSplanificationstage');

it(
    'importe une nouvelle FIF dont le nom d onglet contient modele FIF',
    function (): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Nouveau modèle FIF');
        $sheet->setCellValue('C2', 'Formation au sauvetage');
        $sheet->setCellValue('C4', 'SAUVETAGE');
        $sheet->setCellValue('C6', 'Formation au sauvetage en mer');

        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'fif-'
            . Str::uuid()
            . '.xlsx';

        try {
            (new Xlsx($spreadsheet))->save($path);

            $result = app(FifStageImporter::class)
                ->import($path);

            expect($result)
                ->generation->toBe('nouvelle')
                ->action->toBe('créée')
                ->libelle_court->toBe('SAUVETAGE');

            $stage = Stage::query()->sole();

            expect($stage)
                ->fif_generation->toBe('nouvelle')
                ->intitule_formation->toBe(
                    'Formation au sauvetage'
                )
                ->libelle_court->toBe('SAUVETAGE')
                ->libelle_long->toBe(
                    'Formation au sauvetage en mer'
                );
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
);

it(
    'importe la feuille reconnue par un marqueur du nouveau modele',
    function (): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FIF 2026');
        $sheet->setCellValue(
            'A1',
            'Typologie de la formation'
        );
        $sheet->setCellValue('C2', 'Formation navigation');
        $sheet->setCellValue('C4', 'NAVIGATION');
        $sheet->setCellValue(
            'C6',
            'Formation à la navigation maritime'
        );

        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'fif-'
            . Str::uuid()
            . '.xlsx';

        try {
            (new Xlsx($spreadsheet))->save($path);

            $result = app(FifStageImporter::class)
                ->import($path);

            expect($result)
                ->generation->toBe('nouvelle')
                ->action->toBe('créée')
                ->libelle_court->toBe('NAVIGATION');

            expect(Stage::query()->sole())
                ->intitule_formation->toBe(
                    'Formation navigation'
                );
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
);

it(
    'extrait toutes les informations de la fiche identite formation',
    function (): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FIF 2026');
        $sheet->setCellValue(
            'C1',
            "FICHE D'IDENTITE de FORMATION"
        );
        $sheet->setCellValue('C2', 'Intitulé de la formation');
        $sheet->setCellValue(
            'C3',
            "Certificat Mentor\nC/MTR"
        );
        $sheet->setCellValue('F2', 'Service responsable');
        $sheet->setCellValue(
            'F3',
            'FPS / Pôle appui à la formation'
        );
        $sheet->setCellValue(
            'F4',
            "SI d'enregistrement de la qualification"
        );
        $sheet->setCellValue('F5', 'COMMETE/RH@poseidon');
        $sheet->setCellValue('A8', 'Grades :');
        $sheet->setCellValue('C8', 'MT à MJR');
        $sheet->setCellValue('E8', 'Durée (en j) :');
        $sheet->setCellValue('G8', '3');
        $sheet->setCellValue('A9', 'Niveau :');
        $sheet->setCellValue('C9', 'BS/BM');
        $sheet->setCellValue('E9', 'Capacité nominale :');
        $sheet->setCellValue('G9', '8 à 12 stagiaires');
        $sheet->setCellValue('E10', 'Lieu :');
        $sheet->setCellValue('G10', 'FPS');
        $sheet->setCellValue('A11', 'PRE-REQUIS');
        $sheet->setCellValue(
            'A12',
            "Posséder le BS ou le BST\nÊtre désigné mentor"
        );
        $sheet->setCellValue(
            'A14',
            'Mentor des marins BAT en cursus FCM'
        );
        $sheet->setCellValue(
            'A16',
            'Développer un parcours individualisé'
        );

        for ($row = 18; $row <= 26; $row++) {
            $sheet->setCellValue(
                'A' . $row,
                'Domaine ' . ($row - 17)
            );
            $sheet->setCellValue(
                'E' . $row,
                'Compétence ' . ($row - 17)
            );
        }

        $sheet->setCellValue('C28', 'Questionnaire');
        $sheet->setCellValue('C29', 'Mise en situation');
        $sheet->setCellValue('C30', 'Examen final');
        $sheet->setCellValue('A33', 'Travaux de groupes');
        $sheet->setCellValue('D33', 'Travaux pratiques');
        $sheet->setCellValue('A34', 'Visite FREMM');
        $sheet->setCellValue('D34', 'Visite du bâtiment');
        $sheet->setCellValue('A35', 'Schéma');
        $sheet->setCellValue('D35', 'Support visuel');
        $sheet->setCellValue('A36', 'Simulateur');
        $sheet->setCellValue('D36', 'Mise en situation');
        $sheet->setCellValue('A37', 'Vidéo');
        $sheet->setCellValue('D37', 'Démonstration');
        $sheet->setCellValue('A38', 'Écran interactif');
        $sheet->setCellValue('D38', 'Oui');
        $sheet->setCellValue('A39', 'Autre');
        $sheet->setCellValue('D39', 'Jeux sérieux');

        Stage::create([
            'libelle_court' =>
                "Certificat Mentor\nC/MTR",
            'typologie' =>
                'Valeur incorrectement importée',
            'actif' => true,
        ]);

        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'fif-'
            . Str::uuid()
            . '.xlsx';

        try {
            (new Xlsx($spreadsheet))->save($path);

            $result = app(FifStageImporter::class)
                ->import($path);

            expect($result)
                ->generation->toBe('nouvelle')
                ->action->toBe('mise à jour')
                ->prerequis->toBe(2)
                ->modules->toBe(9);

            $stage = Stage::query()->sole();

            expect($stage)
                ->typologie->toBeNull()
                ->service_emetteur->toBe(
                    'FPS / Pôle appui à la formation'
                )
                ->si_enregistrement_qualification->toBe(
                    'COMMETE/RH@poseidon'
                )
                ->echelle_grades->toBe('MT à MJR')
                ->niveau_brevet->toBe('BS/BM')
                ->duree_jours->toBe('3.0')
                ->capacite_min->toBe(8)
                ->capacite_max->toBe(12)
                ->lieux_formation->toBe('FPS')
                ->fonctions_visees->toBe(
                    'Mentor des marins BAT en cursus FCM'
                )
                ->objectif_formation->toBe(
                    'Développer un parcours individualisé'
                )
                ->evaluation_diagnostique->toBe('Questionnaire')
                ->evaluation_formative->toBe('Mise en situation')
                ->evaluation_certificative->toBe('Examen final')
                ->pedagogie_groupes->toBe('Travaux pratiques')
                ->pedagogie_visite->toBe('Visite du bâtiment')
                ->pedagogie_video->toBe('Démonstration')
                ->pedagogie_tableau_interactif->toBe('Oui')
                ->pedagogie_autre->toContain('Support visuel')
                ->pedagogie_autre->toContain('Mise en situation')
                ->pedagogie_autre->toContain('Jeux sérieux');

            expect($stage->prerequis()->pluck('libelle')->all())
                ->toBe([
                    'Posséder le BS ou le BST',
                    'Être désigné mentor',
                ])
                ->and($stage->fifModules()->count())
                ->toBe(9)
                ->and(
                    $stage->fifModules()
                        ->first()
                        ?->objectifs_competences
                )
                ->toBe('Compétence 1');
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
);
