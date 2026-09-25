<?php

use Guava\Calendar\ValueObjects\EventClickInfo;
use Illuminate\Database\Eloquent\Model;
use Modules\FPSplanificationstage\Filament\Public\Pages\SessionDetail;
use Modules\FPSplanificationstage\Filament\Widgets\PlanningCalendar;

uses(Tests\TestCase::class);
uses()->group('FPSplanificationstage');

it('uses the exact Guava event click signature', function () {
    $method =
        new ReflectionMethod(
            PlanningCalendar::class,
            'onEventClick'
        );

    $parameters =
        $method->getParameters();

    expect($parameters)
        ->toHaveCount(3);

    expect(
        (string) $parameters[0]->getType()
    )->toBe(
        EventClickInfo::class
    );

    expect(
        (string) $parameters[1]->getType()
    )->toBe(
        Model::class
    );

    expect(
        (string) $parameters[2]->getType()
    )->toBe('?string');
});

it('enables clicks and attaches the session model', function () {
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
            'protected bool $eventClickEnabled = true;'
        )
        ->toContain(
            'CalendarEvent::make($session)'
        )
        ->toContain(
            SessionDetail::class
        )
        ->toContain(
            'SessionDetail::getUrl('
        )
        ->not->toContain(
            '->url('
        );
});

it('hides saturday and sunday only in week view', function () {
    $week =
        (new ReflectionClass(
            PlanningCalendar::class
        ))
            ->newInstanceWithoutConstructor();

    $week->viewMode = 'week';

    expect(
        $week->getOptions()['hiddenDays']
    )->toBe([0, 6]);

    $month =
        (new ReflectionClass(
            PlanningCalendar::class
        ))
            ->newInstanceWithoutConstructor();

    $month->viewMode = 'month';

    expect(
        $month->getOptions()['hiddenDays']
    )->toBe([]);

    $list =
        (new ReflectionClass(
            PlanningCalendar::class
        ))
            ->newInstanceWithoutConstructor();

    $list->viewMode = 'list';

    expect(
        $list->getOptions()['hiddenDays']
    )->toBe([]);
});
