<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Models\Stage;

class BesoinPeriodeService
{
    public const TYPE_DATE_FIXE = 'dates_fixes';
    public const TYPE_PLAGE_DISPONIBILITE = 'plage';
    public const TYPE_PLAGE_DEMARRAGE = 'plage_demarrage';
    public const TYPE_INDISPONIBILITE = 'indisponibilite';

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_DATE_FIXE,
            self::TYPE_PLAGE_DISPONIBILITE,
            self::TYPE_PLAGE_DEMARRAGE,
            self::TYPE_INDISPONIBILITE,
        ];
    }

    public static function isRangeMode(?string $type): bool
    {
        return in_array(
            $type,
            [
                self::TYPE_PLAGE_DISPONIBILITE,
                self::TYPE_PLAGE_DEMARRAGE,
                self::TYPE_INDISPONIBILITE,
            ],
            true
        );
    }

    public static function minimumWorkingDays(float|int|string|null $duree): int
    {
        $value = (float) ($duree ?? 0);

        if ($value <= 0) {
            return 0;
        }

        /*
         * Les besoins utilisent des dates entières. Un stage de 2,5 jours
         * occupe donc 3 dates ouvrées (la dernière étant une demi-journée).
         */
        return max(1, (int) ceil($value));
    }

    /** Search future dates on either side of an unavailable period. */
    public static function unavailableSearchBounds(BesoinFormation $besoin): array
    {
        $start = Carbon::today();
        $end = Carbon::parse($besoin->date_fin_souhaitee)->max($start)->copy()->addYear()->endOfDay();

        return [$start, $end];
    }

    public static function avoidsUnavailablePeriod(BesoinFormation $besoin, Carbon $start, Carbon $end): bool
    {
        if (!$besoin->date_debut_souhaitee || !$besoin->date_fin_souhaitee) {
            return false;
        }

        return $start->gte(Carbon::today())
            && ($end->lt(Carbon::parse($besoin->date_debut_souhaitee)->startOfDay())
                || $start->gt(Carbon::parse($besoin->date_fin_souhaitee)->endOfDay()));
    }

    public static function countWorkingDays(mixed $start, mixed $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        $cursor = Carbon::parse($start)->startOfDay();
        $finish = Carbon::parse($end)->startOfDay();

        if ($finish->lt($cursor)) {
            return 0;
        }

        $count = 0;

        while ($cursor->lte($finish)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    public static function minimumEndDate(
        int|string|null $stageId,
        mixed $start
    ): ?Carbon {
        if (! $stageId || ! $start) {
            return null;
        }

        $stage = Stage::query()->find((int) $stageId);

        if (! $stage) {
            return null;
        }

        $days = self::minimumWorkingDays($stage->duree_jours);

        if ($days <= 0) {
            return null;
        }

        $date = Carbon::parse($start)->startOfDay();

        while ($date->isWeekend()) {
            $date->addDay();
        }

        $remaining = $days - 1;

        while ($remaining > 0) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $remaining--;
            }
        }

        return $date;
    }

    public static function calculatedFixedEndDate(
        int|string|null $stageId,
        mixed $start
    ): ?Carbon {
        return self::minimumEndDate($stageId, $start);
    }

    public static function helperText(
        int|string|null $stageId,
        ?string $type
    ): string {
        if ($type === self::TYPE_INDISPONIBILITE) {
            return 'Le stage doit être entièrement réalisé en dehors de la période d’indisponibilité.';
        }
        if (! $stageId) {
            return 'Sélectionnez d’abord un stage pour connaître sa durée.';
        }

        $stage = Stage::query()->find((int) $stageId);

        if (! $stage) {
            return 'Stage introuvable.';
        }

        $duree = (float) ($stage->duree_jours ?? 0);

        if ($duree <= 0) {
            return 'La durée du stage n’est pas renseignée dans le catalogue.';
        }

        $dureeLabel = rtrim(
            rtrim(number_format($duree, 1, ',', ''), '0'),
            ','
        );

        if ($type === self::TYPE_DATE_FIXE) {
            return sprintf(
                'Durée : %s jour(s). La date de fin sera calculée automatiquement en jours ouvrés.',
                $dureeLabel
            );
        }

        if ($type === self::TYPE_PLAGE_DEMARRAGE) {
            return sprintf(
                'Durée : %s jour(s). Le début peut être choisi dans cette période ; la fin du stage peut dépasser cette période.',
                $dureeLabel
            );
        }

        return sprintf(
            'Durée : %s jour(s). La période disponible doit contenir au minimum %d jour(s) ouvré(s) et le stage complet doit y tenir.',
            $dureeLabel,
            self::minimumWorkingDays($duree)
        );
    }

    public static function validateValues(
        int|string|null $stageId,
        ?string $type,
        mixed $start,
        mixed $end
    ): ?string {
        if (! in_array($type, self::allowedTypes(), true)) {
            return 'Mode de planification invalide.';
        }

        if (! $stageId) {
            return 'Le stage doit être sélectionné.';
        }

        $stage = Stage::query()->find((int) $stageId);

        if (! $stage) {
            return 'Le stage sélectionné est introuvable.';
        }

        $duree = (float) ($stage->duree_jours ?? 0);

        if ($duree <= 0) {
            return 'La durée du stage n’est pas renseignée dans le catalogue.';
        }

        if (! $start) {
            return 'La date de début est obligatoire.';
        }

        $startDate = Carbon::parse($start)->startOfDay();

        if ($type === self::TYPE_DATE_FIXE) {
            if ($startDate->isWeekend()) {
                return 'La date de début imposée doit être un jour ouvré.';
            }

            return null;
        }

        if (! $end) {
            return 'La date de fin de la période est obligatoire.';
        }

        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            return 'La date de fin de la période doit être postérieure ou égale à la date de début.';
        }

        /*
         * RÈGLE IMPÉRATIVE UNIQUEMENT POUR LA PLAGE DE DISPONIBILITÉ :
         * le stage complet doit tenir dans la fenêtre, donc celle-ci doit
         * contenir au moins autant de jours ouvrés que la durée réelle.
         *
         * Une plage de démarrage est différente : seul le JOUR DE DÉBUT
         * doit se trouver dans la fenêtre ; la fin peut la dépasser.
         */
        if ($type === self::TYPE_PLAGE_DISPONIBILITE) {
            $minimum = self::minimumWorkingDays($duree);
            $selected = self::countWorkingDays($startDate, $endDate);

            if ($selected < $minimum) {
                $minimumEnd = self::minimumEndDate($stageId, $startDate);
                $dureeLabel = rtrim(
                    rtrim(number_format($duree, 1, ',', ''), '0'),
                    ','
                );

                return sprintf(
                    'Période disponible trop courte : %d jour(s) ouvré(s) sélectionné(s), minimum %d pour un stage de %s jour(s)%s.',
                    $selected,
                    $minimum,
                    $dureeLabel,
                    $minimumEnd
                        ? ' (fin minimale : ' . $minimumEnd->format('d/m/Y') . ')'
                        : ''
                );
            }
        }

        return null;
    }

    public static function validateBesoin(BesoinFormation $besoin): ?string
    {
        return self::validateValues(
            $besoin->stage_id,
            $besoin->type_periode,
            $besoin->date_debut_souhaitee,
            $besoin->date_fin_souhaitee
        );
    }
}
