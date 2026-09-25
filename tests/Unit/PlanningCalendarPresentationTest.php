<?php

use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Filament\Widgets\PlanningCalendar;
use Modules\FPSplanificationstage\Models\SessionStage;

uses(Tests\TestCase::class);
uses()->group('FPSplanificationstage');

function planningCalendarInvoke(
    string $method,
    mixed ...$arguments
): mixed {
    $class =
        new ReflectionClass(
            PlanningCalendar::class
        );

    $widget =
        $class->newInstanceWithoutConstructor();

    $reflection =
        new ReflectionMethod(
            PlanningCalendar::class,
            $method
        );

    $reflection->setAccessible(true);

    return $reflection->invoke(
        $widget,
        ...$arguments
    );
}

it('puts the longest stage first when sessions start together', function () {
    $long =
        new SessionStage([
            'stage_id' => 1,
            'debut' => '2026-09-14 10:00:00',
            'fin' => '2026-09-16 16:00:00',
            'statut' => 'planifiee',
        ]);

    $short =
        new SessionStage([
            'stage_id' => 2,
            'debut' => '2026-09-14 10:00:00',
            'fin' => '2026-09-14 16:00:00',
            'statut' => 'planifiee',
        ]);

    expect(
        planningCalendarInvoke(
            'compareSessions',
            $long,
            $short
        )
    )->toBeLessThan(0);
});

it('uses different colors for different active stages', function () {
    $first =
        new SessionStage([
            'stage_id' => 1,
            'statut' => 'confirmee',
        ]);

    $second =
        new SessionStage([
            'stage_id' => 2,
            'statut' => 'confirmee',
        ]);

    expect(
        planningCalendarInvoke(
            'eventColor',
            $first
        )
    )->not->toBe(
        planningCalendarInvoke(
            'eventColor',
            $second
        )
    );
});

it('keeps the same color for the same stage', function () {
    $planned =
        new SessionStage([
            'stage_id' => 4,
            'statut' => 'planifiee',
        ]);

    $confirmed =
        new SessionStage([
            'stage_id' => 4,
            'statut' => 'confirmee',
        ]);

    expect(
        planningCalendarInvoke(
            'eventColor',
            $planned
        )
    )->toBe(
        planningCalendarInvoke(
            'eventColor',
            $confirmed
        )
    );
});

it('keeps cancelled sessions red', function () {
    $session =
        new SessionStage([
            'stage_id' => 2,
            'statut' => 'annulee',
        ]);

    expect(
        planningCalendarInvoke(
            'eventColor',
            $session
        )
    )->toBe('#dc2626');
});

it('keeps the click toward the public detail page', function () {
    $reflection =
        new ReflectionClass(
            PlanningCalendar::class
        );

    $source =
        file_get_contents(
            $reflection->getFileName()
        );

    expect($source)
        ->toContain(
            SessionDetail::class
        )
        ->toContain(
            'SessionDetail::getUrl('
        )
        ->not->toContain(
            'SessionStageResource::getUrl'
        );
});
