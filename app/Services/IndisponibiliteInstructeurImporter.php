<?php

namespace Modules\FPSplanificationstage\Services;

use Illuminate\Support\Str;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\Marin;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class IndisponibiliteInstructeurImporter
{
    public function import(string $filePath): array
    {
        $result = [
            'analysees' => 0,
            'creees' => 0,
            'mises_a_jour' => 0,
            'inchangees' => 0,
            'erreurs' => [],
        ];

        $spreadsheet = IOFactory::load($filePath);

        $sheet = $spreadsheet->getSheetByName('Indisponibilités');

        if (! $sheet) {
            throw new RuntimeException(
                'L’onglet "Indisponibilités" est introuvable.'
            );
        }

        [$columns, $rows] = $this->prepareSheet($sheet);

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;

            try {
                $identifiant = $this->stringValue(
                    $this->value($row, $columns, 'identifiant instructeur')
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

                $result['analysees']++;

                $instructeur = $this->findInstructeur(
                    $identifiant,
                    $nom,
                    $prenom
                );

                if (! $instructeur instanceof Marin) {
                    throw new RuntimeException(
                        'Instructeur introuvable ou ambigu.'
                    );
                }

                $dateDebut = $this->dateValue(
                    $this->value($row, $columns, 'date debut')
                );

                $dateFin = $this->dateValue(
                    $this->value($row, $columns, 'date fin')
                );

                if (! $dateDebut || ! $dateFin) {
                    throw new RuntimeException(
                        'Date de début ou date de fin manquante.'
                    );
                }

                if ($dateFin < $dateDebut) {
                    throw new RuntimeException(
                        'La date de fin est antérieure à la date de début.'
                    );
                }

                $journeeEntiere = $this->booleanValue(
                    $this->value($row, $columns, 'journee entiere'),
                    true
                );

                $heureDebut = null;
                $heureFin = null;

                if (! $journeeEntiere) {
                    $heureDebut = $this->timeValue(
                        $this->value($row, $columns, 'heure debut')
                    );

                    $heureFin = $this->timeValue(
                        $this->value($row, $columns, 'heure fin')
                    );

                    if (! $heureDebut || ! $heureFin) {
                        throw new RuntimeException(
                            'Les heures sont obligatoires pour une indisponibilité partielle.'
                        );
                    }

                    if (
                        $dateDebut === $dateFin &&
                        $heureFin <= $heureDebut
                    ) {
                        throw new RuntimeException(
                            'L’heure de fin doit être postérieure à l’heure de début.'
                        );
                    }
                }

                $motif = $this->normalizeMotif(
                    $this->stringValue(
                        $this->value($row, $columns, 'motif')
                    )
                );

                $commentaire = $this->stringValue(
                    $this->value($row, $columns, 'commentaire')
                );

                $actif = $this->booleanValue(
                    $this->value($row, $columns, 'actif'),
                    true
                );

                $matchKey = implode('|', [
                    $instructeur->id,
                    $dateDebut,
                    $heureDebut ?? '',
                    $dateFin,
                    $heureFin ?? '',
                    $journeeEntiere ? '1' : '0',
                ]);

                $payload = [
                    'instructeur_id' => $instructeur->id,
                    'date_debut' => $dateDebut,
                    'heure_debut' => $heureDebut,
                    'date_fin' => $dateFin,
                    'heure_fin' => $heureFin,
                    'journee_entiere' => $journeeEntiere,
                    'motif' => $motif,
                    'commentaire' => $commentaire,
                    'actif' => $actif,
                    'import_match_key' => $matchKey,
                    'dernier_import_at' => now(),
                ];

                $hashData = $payload;
                unset($hashData['dernier_import_at']);

                $hash = hash(
                    'sha256',
                    json_encode(
                        $hashData,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
                );

                $indisponibilite = IndisponibiliteInstructeur::query()
                    ->where('import_match_key', $matchKey)
                    ->first();

                if (! $indisponibilite) {
                    $payload['import_hash'] = $hash;

                    IndisponibiliteInstructeur::create($payload);

                    $result['creees']++;

                    continue;
                }

                if ($indisponibilite->import_hash === $hash) {
                    $indisponibilite->forceFill([
                        'dernier_import_at' => now(),
                    ])->saveQuietly();

                    $result['inchangees']++;

                    continue;
                }

                $payload['import_hash'] = $hash;

                $indisponibilite->fill($payload);
                $indisponibilite->save();

                $result['mises_a_jour']++;
            } catch (Throwable $e) {
                $result['erreurs'][] =
                    "Ligne {$excelRow} : {$e->getMessage()}";
            }
        }

        return $result;
    }

    private function findInstructeur(
        ?string $identifiant,
        ?string $nom,
        ?string $prenom
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

        if ($nom !== null && $prenom !== null) {
            $matches = Marin::withoutGlobalScopes()
                ->whereRaw(
                    'UPPER(TRIM(nom)) = ?',
                    [mb_strtoupper($nom)]
                )
                ->whereRaw(
                    'UPPER(TRIM(prenom)) = ?',
                    [mb_strtoupper($prenom)]
                )
                ->get();

            if ($matches->count() > 1) {
                return false;
            }

            return $matches->first();
        }

        return null;
    }

    private function prepareSheet($sheet): array
    {
        $rows = $sheet->toArray(
            null,
            true,
            false,
            true
        );

        $headerRow = array_shift($rows);

        $columns = [];

        foreach ($headerRow as $column => $header) {
            if ($header === null) {
                continue;
            }

            $columns[
                $this->normalizeHeader((string) $header)
            ] = $column;
        }

        return [$columns, $rows];
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
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

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject($value)
                ->format('Y-m-d');
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat(
                $format,
                trim((string) $value)
            );

            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject($value)
                ->format('H:i:s');
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            $time = \DateTime::createFromFormat(
                $format,
                trim((string) $value)
            );

            if ($time) {
                return $time->format('H:i:s');
            }
        }

        return null;
    }

    private function booleanValue(
        mixed $value,
        bool $default
    ): bool {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        $value = Str::upper(
            Str::ascii(trim((string) $value))
        );

        return in_array(
            $value,
            ['OUI', 'YES', '1', 'X'],
            true
        );
    }

    private function normalizeMotif(?string $motif): ?string
    {
        if ($motif === null) {
            return null;
        }

        $value = Str::lower(
            Str::ascii($motif)
        );

        return match ($value) {
            'conge' => 'conge',
            'mission' => 'mission',
            'formation' => 'formation',
            'service' => 'service',
            'absence' => 'absence',
            default => 'autre',
        };
    }
}
