<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;

it('backs every requirement with the string the booking page reads', function () {
    expect(array_map(
        static fn (GuestFieldRequirement $requirement): string => $requirement->value,
        GuestFieldRequirement::cases(),
    ))->toBe(['hidden', 'optional', 'required']);
});

it('collects a field unless the business hid it', function (GuestFieldRequirement $requirement, bool $collected) {
    expect($requirement->isCollected())->toBe($collected);
})->with([
    'hidden' => [GuestFieldRequirement::Hidden, false],
    'optional' => [GuestFieldRequirement::Optional, true],
    'required' => [GuestFieldRequirement::Required, true],
]);

it('insists on a field only when the business required it', function (GuestFieldRequirement $requirement, bool $required) {
    expect($requirement->isRequired())->toBe($required);
})->with([
    'hidden' => [GuestFieldRequirement::Hidden, false],
    'optional' => [GuestFieldRequirement::Optional, false],
    'required' => [GuestFieldRequirement::Required, true],
]);
