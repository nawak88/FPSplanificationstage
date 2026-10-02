<?php

use Modules\FPSplanificationstage\Services\BesoinPeriodeService;

uses()->group('FPSplanificationstage');

it('allows the supported planning modes', function () {
    expect(BesoinPeriodeService::allowedTypes())->toBe([
        BesoinPeriodeService::TYPE_DATE_FIXE,
        BesoinPeriodeService::TYPE_PLAGE_DISPONIBILITE,
        BesoinPeriodeService::TYPE_PLAGE_DEMARRAGE,
        BesoinPeriodeService::TYPE_INDISPONIBILITE,
    ]);
});

it('detects range mode', function (?string $type, bool $expected) {
    expect(BesoinPeriodeService::isRangeMode($type))->toBe($expected);
})->with([
    'date fixe' => [BesoinPeriodeService::TYPE_DATE_FIXE, false],
    'plage disponibilité' => [BesoinPeriodeService::TYPE_PLAGE_DISPONIBILITE, true],
    'plage démarrage' => [BesoinPeriodeService::TYPE_PLAGE_DEMARRAGE, true],
    'null' => [null, false],
    'indisponibilité' => [BesoinPeriodeService::TYPE_INDISPONIBILITE, true],
    'type inconnu' => ['inconnu', false],
]);

it('minimum working days', function (float|int|string|null $duration, int $expected) {
    expect(BesoinPeriodeService::minimumWorkingDays($duration))->toBe($expected);
})->with([
    'null' => [null, 0],
    'zéro' => [0, 0],
    'négatif' => [-1, 0],
    'un jour' => [1, 1],
    'deux jours' => [2, 2],
    'deux jours et demi' => [2.5, 3],
    'chaîne décimale' => ['4.2', 5],
    'demi-journée' => [0.5, 1],
]);

it('count working days', function (mixed $start, mixed $end, int $expected) {
    expect(BesoinPeriodeService::countWorkingDays($start, $end))->toBe($expected);
})->with([
    'semaine complète lundi vendredi' => ['2026-09-14', '2026-09-18', 5],
    'vendredi à lundi' => ['2026-09-18', '2026-09-21', 2],
    'samedi et dimanche uniquement' => ['2026-09-19', '2026-09-20', 0],
    'samedi à lundi' => ['2026-09-19', '2026-09-21', 1],
    'même lundi' => ['2026-09-21', '2026-09-21', 1],
    'fin avant début' => ['2026-09-22', '2026-09-21', 0],
    'début absent' => [null, '2026-09-21', 0],
    'fin absente' => ['2026-09-21', null, 0],
]);
