<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\IntegrationCategory;
use App\Domains\Integrations\ValueObjects\IntegrationKey;

it('is keyed on the wire by a stable string', function () {
    expect(IntegrationKey::GoogleCalendar->value)->toBe('google_calendar')
        ->and(IntegrationKey::from('google_calendar'))->toBe(IntegrationKey::GoogleCalendar);
});

it('files google calendar under calendar sync', function () {
    expect(IntegrationKey::GoogleCalendar->category())->toBe(IntegrationCategory::CalendarSync)
        ->and(IntegrationCategory::CalendarSync->value)->toBe('calendar_sync');
});

it('connects google calendar through the google provider', function () {
    expect(IntegrationKey::GoogleCalendar->calendarProvider())->toBe(CalendarProvider::Google);
});

it('has a category and a provider for every integration it lists', function (IntegrationKey $key) {
    expect($key->category())->toBeInstanceOf(IntegrationCategory::class)
        ->and($key->calendarProvider())->toBeInstanceOf(CalendarProvider::class);
})->with(IntegrationKey::cases());
