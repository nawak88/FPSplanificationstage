<?php

namespace Modules\FPSplanificationstage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Models\Stage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class FifStageImporter
{
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Le fichier Excel est introuvable.');
        }

        $spreadsheet = IOFactory::load($path);
        $generation = $this->detectGeneration($spreadsheet);

        $parsed = match ($generation) {
            'nouvelle' => $this->parseNouvelleGeneration($spreadsheet, $path),
            'ancienne' => $this->parseAncienneGeneration($spreadsheet, $path),
            default => throw new RuntimeException(
                'Le modèle de FIF n’a pas pu être reconnu.'
            ),
        };

        $payload = $this->withoutNullValues($parsed['payload']);

        foreach (
            $parsed['nullable_fields_to_sync'] ?? []
            as $field
        ) {
            $payload[$field] =
                $parsed['payload'][$field] ?? null;
        }
        $title = $parsed['title'];
        $explicitShortLabel = $parsed['explicit_short_label'];
        $prerequis = $parsed['prerequis'];
        $modules = $parsed['modules'];

        if ($title === null && $explicitShortLabel === null) {
            throw new RuntimeException(
                'La FIF ne contient ni intitulé ni libellé permettant d’identifier le stage.'
            );
        }

        $hashData = [
            'generation' => $generation,
            'payload' => $payload,
            'prerequis' => $prerequis,
            'modules' => $modules,
            'validation' => $parsed['validation'],
            'source' => $parsed['source_snapshot'],
        ];

        unset(
            $hashData['payload']['fif_imported_at'],
            $hashData['payload']['fif_source_fichier'],
            $hashData['payload']['fif_import_hash']
        );

        $hash = hash(
            'sha256',
            json_encode(
                $hashData,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: ''
        );

        return DB::transaction(function () use (
            $payload,
            $title,
            $explicitShortLabel,
            $prerequis,
            $modules,
            $parsed,
            $generation,
            $hash
        ): array {
            $stage = $this->findStage(
                $title,
                $explicitShortLabel
            );

            $created = false;
            $unchanged = false;

            if ($stage === null) {
                if (! isset($payload['libelle_court'])) {
                    $payload['libelle_court'] =
                        $title
                        ?? $explicitShortLabel
                        ?? 'Formation importée';
                }

                if (! isset($payload['libelle_long']) && $title !== null) {
                    $payload['libelle_long'] = $title;
                }

                $payload['fif_import_hash'] = $hash;
                $payload['fif_imported_at'] = now();
                $payload['actif'] = true;

                $stage = Stage::create($payload);
                $created = true;
            } else {
                if ($stage->fif_import_hash === $hash) {
                    $stage->forceFill([
                        'fif_source_fichier' =>
                            $payload['fif_source_fichier'] ?? null,
                        'fif_imported_at' => now(),
                    ])->saveQuietly();

                    $unchanged = true;
                } else {
                    $payload['fif_import_hash'] = $hash;
                    $payload['fif_imported_at'] = now();

                    $stage->fill($payload);
                    $stage->save();
                }
            }

            if (! $unchanged) {
                $this->syncPrerequis(
                    $stage,
                    $prerequis
                );

                $this->syncModules(
                    $stage,
                    $modules
                );
            }

            return [
                'generation' => $generation,
                'generation_label' =>
                    $generation === 'nouvelle'
                        ? 'Nouvelle génération'
                        : 'Ancienne génération',
                'action' =>
                    $created
                        ? 'créée'
                        : ($unchanged ? 'inchangée' : 'mise à jour'),
                'stage_id' => $stage->id,
                'code_stage' => $stage->code_stage,
                'libelle_court' => $stage->libelle_court,
                'prerequis' => count($prerequis),
                'modules' => count($modules),
                'warnings' => $parsed['warnings'],
            ];
        });
    }

    private function detectGeneration(Spreadsheet $spreadsheet): string
    {
        if ($this->findNewGenerationSheet($spreadsheet)) {
            return 'nouvelle';
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $haystack = $this->sheetText($sheet);

            if (
                str_contains(
                    $haystack,
                    'domaines de competences vises'
                )
                || str_contains(
                    $haystack,
                    'criteres de certification'
                )
            ) {
                return 'ancienne';
            }
        }

        throw new RuntimeException(
            'Fichier non reconnu. Utilisez une FIF ancienne génération ou le nouveau modèle FIF.'
        );
    }

    private function parseNouvelleGeneration(
        Spreadsheet $spreadsheet,
        string $path
    ): array {
        $sheet = $this->findNewGenerationSheet(
            $spreadsheet
        );

        if (! $sheet) {
            throw new RuntimeException(
                'L’onglet du nouveau modèle FIF est introuvable.'
            );
        }

        $validationSheet =
            $spreadsheet->getSheetByName('Fiche validation FIF');

        $isIdentityTrainingSheet =
            $this->isIdentityTrainingSheet($sheet);

        if ($isIdentityTrainingSheet) {
            $title = $this->firstNonPlaceholder([
                $this->text($sheet, 'C3'),
            ], [
                'Intitulé de la formation',
            ]);

            $shortLabel = $title;
            $longLabel = $title;

            $service = $this->firstNonPlaceholder([
                $this->text($sheet, 'F3'),
            ], [
                'Service responsable',
            ]);

            $typologie = null;
            $siQualification = $this->firstNonPlaceholder([
                $this->text($sheet, 'F5'),
            ], [
                "SI d'enregistrement de la qualification",
            ]);

            $prerequisText = $this->firstNonPlaceholder([
                $this->text($sheet, 'A12'),
            ], [
                '/',
                'PRE-REQUIS',
            ]);

            $moduleStartRow = 18;
            $moduleEndRow = 26;
            $echelleGrades = $this->text($sheet, 'C8');
            $niveauBrevet = $this->text($sheet, 'C9');
            $dureeJours = $this->number($sheet, 'G8');
            $capaciteMin = $this->minimumInteger(
                $sheet,
                'G9'
            );
            $capaciteMax = $this->maximumInteger(
                $sheet,
                'G9'
            );
            $lieuxFormation = $this->text($sheet, 'G10');
            $fonctionsVisees = $this->text($sheet, 'A14');
            $objectifFormation = $this->text($sheet, 'A16');
            $evaluationDiagnostique =
                $this->text($sheet, 'C28');
            $evaluationFormative =
                $this->text($sheet, 'C29');
            $evaluationCertificative =
                $this->text($sheet, 'C30');
            $pedagogieGroupes = $this->text($sheet, 'D33');
            $pedagogieVisite = $this->text($sheet, 'D34');
            $pedagogieVideo = $this->text($sheet, 'D37');
            $pedagogieTableauInteractif =
                $this->text($sheet, 'D38');
            $pedagogieAutre =
                $this->otherPedagogy($sheet);
        } else {
            $title = $this->firstNonPlaceholder([
                $this->text($sheet, 'C2'),
                $validationSheet
                    ? $this->text($validationSheet, 'B3')
                    : null,
            ], [
                'Intitulé de la formation',
            ]);

            $shortLabel = $this->firstNonPlaceholder([
                $this->text($sheet, 'C4'),
                $validationSheet
                    ? $this->text($validationSheet, 'C4')
                    : null,
                $this->text($sheet, 'C3'),
            ], [
                'Libellé court',
                'Libellé court :',
            ]);

            $longLabel = $this->firstNonPlaceholder([
                $this->text($sheet, 'C6'),
                $validationSheet
                    ? $this->text($validationSheet, 'C5')
                    : null,
                $this->text($sheet, 'C5'),
            ], [
                'Libellé long',
                'Libellé long :',
            ]);

            $service = $this->firstNonPlaceholder([
                $this->text($sheet, 'F3'),
                $validationSheet
                    ? $this->text($validationSheet, 'D4')
                    : null,
                $this->text($sheet, 'F2'),
            ], [
                'SERVICE EMETTEUR',
                'Service Emetteur',
            ]);

            $typologie = $this->firstNonPlaceholder([
                $this->text($sheet, 'F5'),
                $this->text($sheet, 'F4'),
            ], [
                'Typologie de la formation',
            ]);

            $siQualification = $this->firstNonPlaceholder([
                $this->text($sheet, 'F7'),
                $this->text($sheet, 'F6'),
            ], [
                "SI d'enregistrement de la qualification",
            ]);

            $prerequisText = $this->firstNonPlaceholder([
                $this->text($sheet, 'A14'),
            ], [
                '/',
                'PRE-REQUIS',
            ]);

            $moduleStartRow = 20;
            $moduleEndRow = 22;
            $echelleGrades = $this->text($sheet, 'C10');
            $niveauBrevet = $this->text($sheet, 'C11');
            $dureeJours = $this->number($sheet, 'G10');
            $capaciteMin = null;
            $capaciteMax = $this->integer($sheet, 'G11');
            $lieuxFormation = $this->text($sheet, 'G12');
            $fonctionsVisees = $this->text($sheet, 'A16');
            $objectifFormation = $this->text($sheet, 'A18');
            $evaluationDiagnostique =
                $this->text($sheet, 'C25');
            $evaluationFormative =
                $this->text($sheet, 'C26');
            $evaluationCertificative =
                $this->text($sheet, 'C27');
            $pedagogieGroupes = $this->text($sheet, 'D30');
            $pedagogieVisite = $this->text($sheet, 'D31');
            $pedagogieVideo = $this->text($sheet, 'D32');
            $pedagogieTableauInteractif =
                $this->text($sheet, 'D33');
            $pedagogieAutre = $this->text($sheet, 'D34');
        }

        $modules = [];

        for ($row = $moduleStartRow; $row <= $moduleEndRow; $row++) {
            $module = $this->text(
                $sheet,
                'A' . $row
            );

            $objectifs = $this->text(
                $sheet,
                'E' . $row
            );

            if ($module === null && $objectifs === null) {
                continue;
            }

            $modules[] = [
                'ordre' => count($modules) + 1,
                'module' => $module,
                'objectifs_competences' => $objectifs,
                'source' => 'fif',
            ];
        }

        $validation = $this->extractValidation(
            $validationSheet
        );

        $raw = [
            'modele_fif' => $this->sheetSnapshot(
                $sheet,
                1,
                40,
                1,
                8
            ),
            'fiche_validation' =>
                $validationSheet
                    ? $this->sheetSnapshot(
                        $validationSheet,
                        1,
                        13,
                        1,
                        4
                    )
                    : [],
        ];

        $payload = [
            'fif_generation' => 'nouvelle',
            'intitule_formation' =>
                $title ?? $longLabel ?? $shortLabel,
            'service_emetteur' => $service,
            'typologie' => $typologie,
            'si_enregistrement_qualification' =>
                $siQualification,
            'libelle_court' => $shortLabel,
            'libelle_long' => $longLabel,
            'echelle_grades' => $echelleGrades,
            'niveau_brevet' => $niveauBrevet,
            'duree_jours' => $dureeJours,
            'capacite_min' => $capaciteMin,
            'capacite_max' => $capaciteMax,
            'lieux_formation' => $lieuxFormation,
            'fonctions_visees' => $fonctionsVisees,
            'objectif_formation' => $objectifFormation,
            'evaluation_diagnostique' =>
                $evaluationDiagnostique,
            'evaluation_formative' =>
                $evaluationFormative,
            'evaluation_certificative' =>
                $evaluationCertificative,
            'pedagogie_groupes' => $pedagogieGroupes,
            'pedagogie_visite' => $pedagogieVisite,
            'pedagogie_video' => $pedagogieVideo,
            'pedagogie_tableau_interactif' =>
                $pedagogieTableauInteractif,
            'pedagogie_autre' => $pedagogieAutre,
            'fif_validation' =>
                $validation !== []
                    ? $validation
                    : null,
            'fif_donnees_source' => $raw,
            'fif_source_fichier' => basename($path),
            'fif_imported_at' => now(),
            'actif' => true,
        ];

        return [
            'payload' => $payload,
            'title' =>
                $title ?? $longLabel ?? $shortLabel,
            'explicit_short_label' => $shortLabel,
            'prerequis' =>
                $this->makePrerequis(
                    $prerequisText
                ),
            'modules' => $modules,
            'validation' => $validation,
            'source_snapshot' => $raw,
            'warnings' => $this->identityWarnings(
                $title,
                $shortLabel,
                $longLabel
            ),
            'nullable_fields_to_sync' =>
                $isIdentityTrainingSheet
                    ? [
                        'typologie',
                        'evaluation_diagnostique',
                        'evaluation_formative',
                        'evaluation_certificative',
                        'pedagogie_groupes',
                        'pedagogie_visite',
                        'pedagogie_video',
                        'pedagogie_tableau_interactif',
                        'pedagogie_autre',
                    ]
                    : [],
        ];
    }

    private function parseAncienneGeneration(
        Spreadsheet $spreadsheet,
        string $path
    ): array {
        $sheet = $spreadsheet->getSheetByName('Feuil3')
            ?? $this->findSheetContaining(
                $spreadsheet,
                'domaines de competences vises'
            );

        if (! $sheet) {
            throw new RuntimeException(
                'L’onglet de l’ancienne FIF est introuvable.'
            );
        }

        $title = $this->firstNonPlaceholder([
            $this->text($sheet, 'C3'),
            $this->text($sheet, 'C2'),
        ], [
            'Intitulé de la formation',
        ]);

        $service = $this->firstNonPlaceholder([
            $this->text($sheet, 'F3'),
            $this->text($sheet, 'F2'),
        ], [
            'Service responsable',
        ]);

        $prerequisText = $this->text(
            $sheet,
            'A10'
        );

        $domaines = [];
        $criteres = [];

        for ($row = 16; $row <= 18; $row++) {
            $domaine = $this->text(
                $sheet,
                'A' . $row
            );

            $critere = $this->text(
                $sheet,
                'E' . $row
            );

            if ($domaine !== null) {
                $domaines[] = $domaine;
            }

            if ($critere !== null) {
                $criteres[] = $critere;
            }
        }

        $pedagogie = [];

        for ($row = 26; $row <= 32; $row++) {
            $outil = $this->text(
                $sheet,
                'A' . $row
            );

            $contexte = $this->text(
                $sheet,
                'D' . $row
            );

            if ($outil === null && $contexte === null) {
                continue;
            }

            if ($outil !== null && $contexte !== null) {
                $pedagogie[] =
                    $outil . ' : ' . $contexte;
            } else {
                $pedagogie[] =
                    $outil ?? $contexte;
            }
        }

        $raw = [
            'fif_ancienne_generation' =>
                $this->sheetSnapshot(
                    $sheet,
                    1,
                    32,
                    1,
                    8
                ),
        ];

        $payload = [
            'fif_generation' => 'ancienne',
            'intitule_formation' => $title,
            'service_emetteur' => $service,
            'echelle_grades' =>
                $this->text($sheet, 'C6'),
            'niveau_brevet' =>
                $this->text($sheet, 'C7'),
            'duree_jours' =>
                $this->number($sheet, 'G6'),
            'capacite_max' =>
                $this->integer($sheet, 'G7'),
            'lieux_formation' =>
                $this->text($sheet, 'G8'),
            'fonctions_visees' =>
                $this->text($sheet, 'A12'),
            'objectif_formation' =>
                $this->text($sheet, 'A14'),
            'domaines_competences_vises' =>
                $domaines !== []
                    ? implode("\n", $domaines)
                    : null,
            'criteres_certification' =>
                $criteres !== []
                    ? implode("\n", $criteres)
                    : null,
            'evaluation_diagnostique' =>
                $this->text($sheet, 'C21'),
            'evaluation_formative' =>
                $this->text($sheet, 'C22'),
            'evaluation_certificative' =>
                $this->text($sheet, 'C23'),
            'pedagogie_autre' =>
                $pedagogie !== []
                    ? implode("\n", $pedagogie)
                    : null,
            'fif_donnees_source' => $raw,
            'fif_source_fichier' => basename($path),
            'fif_imported_at' => now(),
            'actif' => true,
        ];

        return [
            'payload' => $payload,
            'title' => $title,
            'explicit_short_label' => null,
            'prerequis' =>
                $this->makePrerequis(
                    $prerequisText
                ),
            'modules' => [],
            'validation' => [],
            'source_snapshot' => $raw,
            'warnings' =>
                $title === null
                    ? ['Intitulé de formation non renseigné.']
                    : [],
        ];
    }

    private function findStage(
        ?string $title,
        ?string $shortLabel
    ): ?Stage {
        if ($shortLabel !== null) {
            $matches = Stage::query()
                ->where(
                    'libelle_court',
                    $shortLabel
                )
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException(
                    'Plusieurs stages possèdent le même libellé court : ' .
                    $shortLabel
                );
            }

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        if ($title !== null) {
            $matches = Stage::query()
                ->where(function ($query) use ($title): void {
                    $query
                        ->where(
                            'intitule_formation',
                            $title
                        )
                        ->orWhere(
                            'libelle_long',
                            $title
                        )
                        ->orWhere(
                            'libelle_court',
                            $title
                        );
                })
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException(
                    'Plusieurs stages correspondent à l’intitulé : ' .
                    $title
                );
            }

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    private function syncPrerequis(
        Stage $stage,
        array $prerequis
    ): void {
        $stage->prerequis()
            ->where('source', 'fif')
            ->delete();

        foreach ($prerequis as $item) {
            $stage->prerequis()->create($item);
        }
    }

    private function syncModules(
        Stage $stage,
        array $modules
    ): void {
        $stage->fifModules()
            ->where('source', 'fif')
            ->delete();

        foreach ($modules as $module) {
            $stage->fifModules()->create($module);
        }
    }

    private function makePrerequis(
        ?string $value
    ): array {
        if ($value === null) {
            return [];
        }

        $normalized = trim($value);

        if (
            $normalized === ''
            || $normalized === '/'
        ) {
            return [];
        }

        $parts = preg_split(
            '/(?:\r\n|\r|\n|;|•)+/u',
            $normalized
        ) ?: [];

        $parts = array_values(array_filter(array_map(
            function (string $part): string {
                $part = trim($part);

                return preg_replace(
                    '/^\s*(?:[-–—]|\d+[.)])\s*/u',
                    '',
                    $part
                ) ?? $part;
            },
            $parts
        ), fn (string $part): bool => $part !== ''));

        return array_map(
            fn (string $libelle, int $index): array => [
                'ordre' => $index + 1,
                'libelle' => $libelle,
                'obligatoire' => true,
                'actif' => true,
                'source' => 'fif',
                'source_colonne' => 'FIF',
            ],
            $parts,
            array_keys($parts)
        );
    }

    private function extractValidation(
        ?Worksheet $sheet
    ): array {
        if (! $sheet) {
            return [];
        }

        $items = [];

        for ($row = 7; $row <= 13; $row++) {
            $fonction = $this->text(
                $sheet,
                'A' . $row
            );

            if ($fonction === null) {
                continue;
            }

            $gradeNom = $this->text(
                $sheet,
                'B' . $row
            );

            $dateVisa = $this->text(
                $sheet,
                'C' . $row
            );

            $observations = $this->text(
                $sheet,
                'D' . $row
            );

            if (
                $gradeNom === null
                && $dateVisa === null
                && $observations === null
            ) {
                continue;
            }

            $items[] = [
                'fonction' => $fonction,
                'grade_nom' => $gradeNom,
                'date_visa' => $dateVisa,
                'observations' => $observations,
            ];
        }

        return $items;
    }

    private function identityWarnings(
        ?string $title,
        ?string $shortLabel,
        ?string $longLabel
    ): array {
        $warnings = [];

        /*
         * Le nouveau modèle FIF n'a pas toujours une cellule dédiée
         * contenant l'intitulé : le libellé long, puis le libellé court,
         * servent alors légitimement d'intitulé de formation.
         * On ne génère donc un avertissement que si aucune identité
         * exploitable n'est disponible.
         */
        if (
            $title === null
            && $shortLabel === null
            && $longLabel === null
        ) {
            $warnings[] =
                'Aucun intitulé ni libellé exploitable n’a été trouvé.';
        }

        if ($shortLabel === null) {
            $warnings[] =
                'Libellé court non renseigné.';
        }

        if ($longLabel === null) {
            $warnings[] =
                'Libellé long non renseigné.';
        }

        return $warnings;
    }

    private function firstNonPlaceholder(
        array $values,
        array $placeholders
    ): ?string {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $normalizedValue =
                $this->normalize($value);

            $isPlaceholder = false;

            foreach ($placeholders as $placeholder) {
                $normalizedPlaceholder =
                    $this->normalize($placeholder);

                if (
                    $normalizedValue === $normalizedPlaceholder
                    || str_starts_with(
                        $normalizedValue,
                        $normalizedPlaceholder . ' '
                    )
                ) {
                    $isPlaceholder = true;
                    break;
                }
            }

            if (! $isPlaceholder) {
                return $value;
            }
        }

        return null;
    }

    private function findSheetContaining(
        Spreadsheet $spreadsheet,
        string $needle
    ): ?Worksheet {
        $needle = $this->normalize($needle);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (
                str_contains(
                    $this->sheetText($sheet),
                    $needle
                )
            ) {
                return $sheet;
            }
        }

        return null;
    }

    private function findNewGenerationSheet(
        Spreadsheet $spreadsheet
    ): ?Worksheet {
        $exactSheet =
            $spreadsheet->getSheetByName('Modèle FIF');

        if ($exactSheet) {
            return $exactSheet;
        }

        $markers = [
            'modules developpes',
            'si d enregistrement de la qualification',
            'typologie de la formation',
        ];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $haystack = $this->sheetText($sheet);

            foreach ($markers as $marker) {
                if (str_contains($haystack, $marker)) {
                    return $sheet;
                }
            }
        }

        return $this->findSheetWithTitleContaining(
            $spreadsheet,
            'modele fif'
        );
    }

    private function isIdentityTrainingSheet(
        Worksheet $sheet
    ): bool {
        $heading = $this->normalize(
            $this->text($sheet, 'C1') ?? ''
        );

        $durationLabel = $this->normalize(
            $this->text($sheet, 'E8') ?? ''
        );

        return str_contains(
            $heading,
            'fiche d identite de formation'
        ) && str_contains(
            $durationLabel,
            'duree en j'
        );
    }

    private function otherPedagogy(
        Worksheet $sheet
    ): ?string {
        $items = [];

        foreach ([35, 36, 39] as $row) {
            $value = $this->text(
                $sheet,
                'D' . $row
            );

            if ($value === null) {
                continue;
            }

            $label = $this->text(
                $sheet,
                'A' . $row
            );

            $items[] = $label !== null
                ? $label . ' : ' . $value
                : $value;
        }

        return $items === []
            ? null
            : implode("\n", $items);
    }

    private function findSheetWithTitleContaining(
        Spreadsheet $spreadsheet,
        string $needle
    ): ?Worksheet {
        $needle = $this->normalize($needle);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (
                str_contains(
                    $this->normalize($sheet->getTitle()),
                    $needle
                )
            ) {
                return $sheet;
            }
        }

        return null;
    }

    private function sheetText(
        Worksheet $sheet
    ): string {
        $parts = [];

        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();

                if ($value instanceof RichText) {
                    $value = $value->getPlainText();
                }

                if (
                    is_scalar($value)
                    && trim((string) $value) !== ''
                ) {
                    $parts[] = (string) $value;
                }
            }
        }

        return $this->normalize(
            implode(' ', $parts)
        );
    }

    private function sheetSnapshot(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        int $startColumn,
        int $endColumn
    ): array {
        $result = [];

        for ($row = $startRow; $row <= $endRow; $row++) {
            $rowData = [];

            for (
                $column = $startColumn;
                $column <= $endColumn;
                $column++
            ) {
                $coordinate =
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                        $column
                    ) . $row;

                $value = $this->text(
                    $sheet,
                    $coordinate
                );

                if ($value !== null) {
                    $rowData[$coordinate] = $value;
                }
            }

            if ($rowData !== []) {
                $result[(string) $row] = $rowData;
            }
        }

        return $result;
    }

    private function text(
        Worksheet $sheet,
        string $coordinate
    ): ?string {
        $cell = $sheet->getCell($coordinate);
        $value = $cell->getCalculatedValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }

    private function number(
        Worksheet $sheet,
        string $coordinate
    ): ?float {
        $value = $sheet
            ->getCell($coordinate)
            ->getCalculatedValue();

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = Str::ascii(
            trim((string) $value)
        );

        $text = str_replace(
            ',',
            '.',
            $text
        );

        if (
            preg_match(
                '/-?\d+(?:\.\d+)?/',
                $text,
                $matches
            ) === 1
        ) {
            return (float) $matches[0];
        }

        return null;
    }

    private function integer(
        Worksheet $sheet,
        string $coordinate
    ): ?int {
        $number = $this->number(
            $sheet,
            $coordinate
        );

        return $number === null
            ? null
            : (int) round($number);
    }

    private function maximumInteger(
        Worksheet $sheet,
        string $coordinate
    ): ?int {
        $value = $this->text(
            $sheet,
            $coordinate
        );

        if (
            $value === null
            || preg_match_all(
                '/\d+/',
                $value,
                $matches
            ) === 0
        ) {
            return null;
        }

        return max(array_map(
            'intval',
            $matches[0]
        ));
    }

    private function minimumInteger(
        Worksheet $sheet,
        string $coordinate
    ): ?int {
        $value = $this->text(
            $sheet,
            $coordinate
        );

        if (
            $value === null
            || preg_match_all(
                '/\d+/',
                $value,
                $matches
            ) === 0
        ) {
            return null;
        }

        return min(array_map(
            'intval',
            $matches[0]
        ));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function withoutNullValues(array $values): array
    {
        return array_filter(
            $values,
            fn ($value): bool => $value !== null
        );
    }
}
