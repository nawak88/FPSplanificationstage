<?php

namespace Modules\FPSplanificationstage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Models\Marin;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class InstructeurStageImporter
{
    public function import(string $filePath): array
    {
        $result = [
            'instructeurs_crees' => 0,
            'instructeurs_mis_a_jour' => 0,
            'instructeurs_inchanges' => 0,
            'associations_creees' => 0,
            'associations_mises_a_jour' => 0,
            'associations_inchangees' => 0,
            'erreurs' => [],
        ];

        $spreadsheet = IOFactory::load($filePath);

        $this->importInstructeurs(
            $spreadsheet,
            $result
        );

        $this->importAssociations(
            $spreadsheet,
            $result
        );

        return $result;
    }

    private function importInstructeurs(
        $spreadsheet,
        array &$result
    ): void {
        $sheet = $spreadsheet->getSheetByName('Instructeurs');

        if (! $sheet) {
            throw new RuntimeException(
                'L’onglet "Instructeurs" est introuvable.'
            );
        }

        [$columns, $rows] = $this->prepareSheet($sheet);

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;

            try {
                $nom = $this->stringValue(
                    $this->value($row, $columns, 'nom')
                );

                $prenom = $this->stringValue(
                    $this->value($row, $columns, 'prenom')
                );

                if ($nom === null && $prenom === null) {
                    continue;
                }

                if ($nom === null || $prenom === null) {
                    throw new RuntimeException(
                        'Nom ou prénom manquant.'
                    );
                }

                $identifiant = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'identifiant interne'
                    )
                );

                $email = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'email'
                    )
                );

                $instructeur = app(
                    StagiaireResolver::class
                )->resolve([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'matricule' => $identifiant,
                    'email' => $email,
                ]);

                if ($instructeur->wasRecentlyCreated) {
                    $result['instructeurs_crees']++;

                    continue;
                }

                if ($instructeur->wasChanged()) {
                    $result['instructeurs_mis_a_jour']++;

                    continue;
                }

                $result['instructeurs_inchanges']++;
            } catch (Throwable $e) {
                $result['erreurs'][] =
                    "Instructeurs ligne {$excelRow} : {$e->getMessage()}";
            }
        }
    }

    private function importAssociations(
        $spreadsheet,
        array &$result
    ): void {
        $sheet = $spreadsheet->getSheetByName(
            'Stages délivrés'
        );

        if (! $sheet) {
            throw new RuntimeException(
                'L’onglet "Stages délivrés" est introuvable.'
            );
        }

        [$columns, $rows] = $this->prepareSheet($sheet);

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;

            try {
                $identifiant = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'identifiant instructeur'
                    )
                );

                $nom = $this->stringValue(
                    $this->value($row, $columns, 'nom')
                );

                $prenom = $this->stringValue(
                    $this->value($row, $columns, 'prenom')
                );

                if (
                    $identifiant === null &&
                    $nom === null &&
                    $prenom === null
                ) {
                    continue;
                }

                $instructeur = $this->findInstructeur(
                    $identifiant,
                    null,
                    ($nom && $prenom)
                        ? $this->makeInstructeurMatchKey(
                            $nom,
                            $prenom
                        )
                        : null
                );

                if (! $instructeur instanceof Marin) {
                    throw new RuntimeException(
                        'Instructeur introuvable ou ambigu.'
                    );
                }

                $codeStage = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'code stage'
                    )
                );

                $numeroCatalogue = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'numero catalogue'
                    )
                );

                $libelleStage = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'libelle du stage'
                    )
                );

                $stage = $this->findStage(
                    $codeStage,
                    $numeroCatalogue,
                    $libelleStage
                );

                if (! $stage instanceof Stage) {
                    throw new RuntimeException(
                        'Stage introuvable ou ambigu.'
                    );
                }

                $role = Str::lower(
                    Str::ascii(
                        $this->stringValue(
                            $this->value(
                                $row,
                                $columns,
                                'role'
                            )
                        ) ?? 'indifferent'
                    )
                );

                $role = match ($role) {
                    'principal' => 'principal',
                    'suppleant' => 'suppleant',
                    default => 'indifferent',
                };

                $actif = $this->booleanValue(
                    $this->value(
                        $row,
                        $columns,
                        'actif'
                    ),
                    true
                );

                $commentaire = $this->stringValue(
                    $this->value(
                        $row,
                        $columns,
                        'commentaire'
                    )
                );

                $existing = DB::table(
                    'instructeur_stage'
                )
                    ->where(
                        'instructeur_id',
                        $instructeur->id
                    )
                    ->where(
                        'stage_id',
                        $stage->id
                    )
                    ->first();

                $pivotData = [
                    'role' => $role,
                    'actif' => $actif,
                    'commentaire' => $commentaire,
                    'source' => 'excel',
                    'dernier_import_at' => now(),
                    'updated_at' => now(),
                ];

                if (! $existing) {
                    DB::table('instructeur_stage')->insert([
                        'instructeur_id' => $instructeur->id,
                        'stage_id' => $stage->id,
                        ...$pivotData,
                        'created_at' => now(),
                    ]);

                    $result['associations_creees']++;

                    continue;
                }

                $changed =
                    $existing->role !== $role ||
                    (bool) $existing->actif !== $actif ||
                    ($existing->commentaire ?? null) !== $commentaire ||
                    $existing->source !== 'excel';

                if (! $changed) {
                    DB::table('instructeur_stage')
                        ->where('id', $existing->id)
                        ->update([
                            'dernier_import_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $result['associations_inchangees']++;

                    continue;
                }

                DB::table('instructeur_stage')
                    ->where('id', $existing->id)
                    ->update($pivotData);

                $result['associations_mises_a_jour']++;
            } catch (Throwable $e) {
                $result['erreurs'][] =
                    "Stages délivrés ligne {$excelRow} : {$e->getMessage()}";
            }
        }
    }

    private function prepareSheet($sheet): array
    {
        $rows = $sheet->toArray(
            null,
            true,
            false,
            true
        );

        if (count($rows) < 1) {
            throw new RuntimeException(
                "L’onglet {$sheet->getTitle()} est vide."
            );
        }

        $headerRow = array_shift($rows);

        $columns = [];

        foreach ($headerRow as $column => $header) {
            if ($header === null) {
                continue;
            }

            $columns[
                $this->normalizeHeader(
                    (string) $header
                )
            ] = $column;
        }

        return [$columns, $rows];
    }

    private function findInstructeur(
        ?string $identifiant,
        ?string $email,
        ?string $matchKey
    ): Marin|false|null {
        if ($identifiant !== null) {
            $matches = Marin::withoutGlobalScopes()
                ->whereRaw(
                    'UPPER(TRIM(matricule)) = ?',
                    [mb_strtoupper($identifiant)]
                )
                ->get();

            if ($matches->count() > 1) {
                return false;
            }

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        if ($email !== null) {
            $matches = Marin::withoutGlobalScopes()
                ->whereRaw(
                    'LOWER(TRIM(email)) = ?',
                    [mb_strtolower($email)]
                )
                ->get();

            if ($matches->count() > 1) {
                return false;
            }

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        if ($matchKey !== null) {
            [$nom, $prenom] =
                explode('|', $matchKey, 2);

            $matches = Marin::withoutGlobalScopes()
                ->whereRaw(
                    'LOWER(nom) = ?',
                    [$nom]
                )
                ->whereRaw(
                    'LOWER(prenom) = ?',
                    [$prenom]
                )
                ->get();

            if ($matches->count() > 1) {
                return false;
            }

            return $matches->first();
        }

        return null;
    }

    private function findStage(
        ?string $code,
        ?string $numero,
        ?string $libelle
    ): Stage|false|null {
        foreach ([
            ['code_stage', $code],
            ['numero_externe', $numero],
            ['libelle_court', $libelle],
        ] as [$column, $value]) {
            if ($value === null) {
                continue;
            }

            $matches = Stage::query()
                ->where($column, $value)
                ->get();

            if ($matches->count() > 1) {
                return false;
            }

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    private function makeInstructeurMatchKey(
        string $nom,
        string $prenom
    ): string {
        return Str::lower(
            Str::ascii(
                trim($nom) . '|' . trim($prenom)
            )
        );
    }

    private function value(
        array $row,
        array $columns,
        string $header
    ): mixed {
        return isset($columns[$header])
            ? ($row[$columns[$header]] ?? null)
            : null;
    }

    private function normalizeHeader(
        string $value
    ): string {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function stringValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }

    private function booleanValue(
        mixed $value,
        bool $default
    ): bool {
        if (
            $value === null ||
            trim((string) $value) === ''
        ) {
            return $default;
        }

        $value = Str::upper(
            Str::ascii(
                trim((string) $value)
            )
        );

        if (
            in_array(
                $value,
                ['OUI', 'YES', 'X', '1'],
                true
            )
        ) {
            return true;
        }

        if (
            in_array(
                $value,
                ['NON', 'NO', '0'],
                true
            )
        ) {
            return false;
        }

        return $default;
    }
}
