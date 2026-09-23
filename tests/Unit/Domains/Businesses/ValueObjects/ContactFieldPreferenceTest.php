<?php

declare(strict_types=1);

use App\Domains\Businesses\ValueObjects\ContactFieldPreference;

it('lists every preference by the string the client sends', function () {
    expect(ContactFieldPreference::values())->toBe(['hidden', 'optional', 'required']);
});
