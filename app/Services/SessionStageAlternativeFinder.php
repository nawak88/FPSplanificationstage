<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Modules\FPSplanificationstage\Models\Salle;

class SessionStageAlternativeFinder
{
    public function find(
        array $data,
        ?int $currentSessionId = null
    ): array {
        return [
            'salles' => $this->findSalles(
                $data,
                $currentSessionId
            ),

            'dates' => $this->findDates(
                $data,
                $currentSessionId
            ),
        ];
    }

    public function formatForNotification(
        array $suggestions
    ): string {
        $lignes = [];

        if (! empty($suggestions['salles'])) {
            $lignes[] = 'Salles disponibles :';

            foreach ($suggestions['salles'] as $salle) {
                $lignes[] =
                    '• ' . $salle['label'];
            }
        }

        if (! empty($suggestions['dates'])) {
            if ($lignes !== []) {
                $lignes[] = '';
            }

            $lignes[] = 'Créneaux disponibles :';

            foreach ($suggestions['dates'] as $date) {
                $lignes[] =
                    '• ' . $date['label'];
            }
        }

        return implode("\n", $lignes);
    }

    private function findSalles(
        array $data,
        ?int $currentSessionId
    ): array {
        $debut = $this->toCarbon(
            $data['debut'] ?? null
        );

        $fin = $this->toCarbon(
            $data['fin'] ?? null
        );

        if (! $debut || ! $fin) {
            return [];
        }

        $capaciteMax =
            isset($data['capacite_max'])
            && $data['capacite_max'] !== ''
                ? (int) $data['capacite_max']
                : null;

        $salleActuelle =
            ! empty($data['salle_id'])
                ? (int) $data['salle_id']
                : null;

        $query = Salle::query()
            ->where('actif', true)
            ->orderBy('capacite')
            ->orderBy('nom');

        if ($capaciteMax !== null) {
            $query->where(
                function ($query) use (
                    $capaciteMax
                ): void {
                    $query
                        ->whereNull('capacite')
                        ->orWhere(
                            'capacite',
                            '>=',
                            $capaciteMax
                        );
                }
            );
        }

        if ($salleActuelle) {
            $query->whereKeyNot(
                $salleActuelle
            );
        }

        $detector = app(
            SessionStageConflictDetector::class
        );

        $resultats = [];

        foreach ($query->get() as $salle) {
            /*
             * On teste la session complète avec cette
             * nouvelle salle.
             *
             * Cela permet également de vérifier que
             * les formateurs sont disponibles.
             */
            $candidate = $data;

            $candidate['salle_id'] =
                $salle->id;

            $conflits = $detector->detect(
                $candidate,
                $currentSessionId
            );

            if ($conflits !== []) {
                continue;
            }

            $label =
                ($salle->code
                    ? $salle->code . ' — '
                    : '')
                . $salle->nom;

            if ($salle->capacite !== null) {
                $label .=
                    ' (' .
                    $salle->capacite .
                    ' pers.)';
            }

            $resultats[] = [
                'id' => $salle->id,
                'label' => $label,
            ];

            if (count($resultats) >= 3) {
                break;
            }
        }

        return $resultats;
    }

    private function findDates(
        array $data,
        ?int $currentSessionId
    ): array {
        $debutOriginal = $this->toCarbon(
            $data['debut'] ?? null
        );

        $finOriginal = $this->toCarbon(
            $data['fin'] ?? null
        );

        if (
            ! $debutOriginal
            || ! $finOriginal
            || $finOriginal->lessThanOrEqualTo(
                $debutOriginal
            )
        ) {
            return [];
        }

        $detector = app(
            SessionStageConflictDetector::class
        );

        $resultats = [];

        /*
         * Recherche dans les 60 jours suivants.
         */
        for (
            $decalage = 1;
            $decalage <= 60;
            $decalage++
        ) {
            $debut = $debutOriginal
                ->copy()
                ->addDays($decalage);

            $fin = $finOriginal
                ->copy()
                ->addDays($decalage);

            $candidate = $data;

            $candidate['debut'] =
                $debut->format(
                    'Y-m-d H:i:s'
                );

            $candidate['fin'] =
                $fin->format(
                    'Y-m-d H:i:s'
                );

            $conflits = $detector->detect(
                $candidate,
                $currentSessionId
            );

            if ($conflits !== []) {
                continue;
            }

            $resultats[] = [
                'debut' => $debut->format(
                    'Y-m-d H:i:s'
                ),

                'fin' => $fin->format(
                    'Y-m-d H:i:s'
                ),

                'label' => $this->formatPeriode(
                    $debut,
                    $fin
                ),
            ];

            if (count($resultats) >= 3) {
                break;
            }
        }

        return $resultats;
    }

    private function formatPeriode(
        Carbon $debut,
        Carbon $fin
    ): string {
        if ($debut->isSameDay($fin)) {
            return sprintf(
                '%s de %s à %s',
                $debut->format('d/m/Y'),
                $debut->format('H:i'),
                $fin->format('H:i')
            );
        }

        return sprintf(
            'du %s au %s',
            $debut->format(
                'd/m/Y H:i'
            ),
            $fin->format(
                'd/m/Y H:i'
            )
        );
    }

    private function toCarbon(
        mixed $value
    ): ?Carbon {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        try {
            return Carbon::parse(
                $value
            );
        } catch (\Throwable) {
            return null;
        }
    }
}