<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;

it('names the dedicated calendar after the business', function () {
    expect((new BusinessCalendarProfile('Peluquería Ana', 'Europe/Madrid'))->calendarName())
        ->toBe('Mizita – Peluquería Ana');
});

it('reads local time in the zone of the business', function (string $timezone) {
    expect((new BusinessCalendarProfile('Peluquería Ana', $timezone))->localZone()->getName())->toBe($timezone);
})->with(['Europe/Madrid', 'America/Mexico_City', 'America/Santiago']);
