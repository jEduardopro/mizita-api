<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CalendarSettingsData;
use App\Domains\Businesses\Infrastructure\Http\Resources\CalendarSettingsResource;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  list<BusinessScheduleEntry>|null  $schedule
 */
function calendarSettingsData(
    string $timezone = 'Europe/Madrid',
    string $currencyCode = 'EUR',
    ?array $schedule = null,
): CalendarSettingsData {
    return new CalendarSettingsData(
        timezone: $timezone,
        currencyCode: $currencyCode,
        schedule: $schedule ?? [
            new BusinessScheduleEntry(weekday: 1, startsAt: '09:00', endsAt: '14:00'),
            new BusinessScheduleEntry(weekday: 1, startsAt: '16:00', endsAt: '20:00'),
        ],
    );
}

/**
 * @return array<string, mixed>
 */
function calendarSettingsEnvelope(?CalendarSettingsData $settings = null): array
{
    return (array) CalendarSettingsResource::make($settings ?? calendarSettingsData())
        ->response()
        ->getData(true);
}

/**
 * @return array<string, mixed>
 */
function serializedCalendarSettings(?CalendarSettingsData $settings = null): array
{
    return (array) calendarSettingsEnvelope($settings)['data'];
}

describe('the client contract', function () {
    it('wraps the payload in the data envelope and nothing else', function () {
        expect(array_keys(calendarSettingsEnvelope()))->toBe(['data']);
    });

    it('serializes exactly the three keys the calendar reads', function () {
        expect(array_keys(serializedCalendarSettings()))->toBe(['timezone', 'currency_code', 'schedule']);
    });

    it('serializes the whole payload the calendar reads', function () {
        expect(serializedCalendarSettings())->toBe([
            'timezone' => 'Europe/Madrid',
            'currency_code' => 'EUR',
            'schedule' => [
                ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
                ['weekday' => 1, 'starts_at' => '16:00', 'ends_at' => '20:00'],
            ],
        ]);
    });

    it('declares exactly three keys on every schedule row', function () {
        foreach (serializedCalendarSettings()['schedule'] as $row) {
            expect(array_keys($row))->toBe(['weekday', 'starts_at', 'ends_at']);
        }
    });

    it('never serializes an identity, because the calendar settings belong to the caller business', function () {
        expect(json_encode(serializedCalendarSettings(), JSON_THROW_ON_ERROR))
            ->not->toContain('"id"')
            ->not->toContain('business_id');
    });
});

describe('values that must survive untouched', function () {
    it('keeps the IANA timezone as the business filed it, never an offset', function () {
        expect(serializedCalendarSettings(calendarSettingsData(timezone: 'America/Argentina/Buenos_Aires'))['timezone'])
            ->toBe('America/Argentina/Buenos_Aires');
    });

    it('keeps the local times of the week as HH:mm strings, never instants', function () {
        $row = serializedCalendarSettings(calendarSettingsData(schedule: [
            new BusinessScheduleEntry(weekday: 7, startsAt: '00:00', endsAt: '23:59'),
        ]))['schedule'][0];

        expect($row['weekday'])->toBe(7)
            ->and($row['starts_at'])->toBe('00:00')
            ->and($row['ends_at'])->toBe('23:59');
    });

    it('keeps the weekday an integer', function () {
        expect(serializedCalendarSettings()['schedule'][0]['weekday'])->toBeInt();
    });
});

describe('a business with no hours', function () {
    it('sends an empty json array rather than dropping the key', function () {
        $serialized = serializedCalendarSettings(calendarSettingsData(schedule: []));

        expect($serialized)->toHaveKey('schedule')
            ->and($serialized['schedule'])->toBe([])
            ->and(json_encode($serialized['schedule'], JSON_THROW_ON_ERROR))->toBe('[]');
    });
});
