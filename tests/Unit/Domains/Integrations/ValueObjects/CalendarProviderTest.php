<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\CalendarProvider;

it('reads google back from the string the column stores', function () {
    expect(CalendarProvider::from('google'))->toBe(CalendarProvider::Google)
        ->and(CalendarProvider::Google->value)->toBe('google');
});

it('refuses a provider nobody integrated', function () {
    expect(CalendarProvider::tryFrom('outlook'))->toBeNull();
});
