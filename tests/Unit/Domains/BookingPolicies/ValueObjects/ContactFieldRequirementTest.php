<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;

it('stores each requirement under the string the column check constraint allows', function () {
    expect(array_map(
        static fn (ContactFieldRequirement $requirement): string => $requirement->value,
        ContactFieldRequirement::cases(),
    ))->toBe(['hidden', 'optional', 'required']);
});

it('collects a field the form shows and leaves out a field it hides', function (ContactFieldRequirement $requirement, bool $collected) {
    expect($requirement->isCollected())->toBe($collected);
})->with([
    'hidden' => [ContactFieldRequirement::Hidden, false],
    'optional' => [ContactFieldRequirement::Optional, true],
    'required' => [ContactFieldRequirement::Required, true],
]);

it('requires only the field the business marked as required', function (ContactFieldRequirement $requirement, bool $required) {
    expect($requirement->isRequired())->toBe($required);
})->with([
    'hidden' => [ContactFieldRequirement::Hidden, false],
    'optional' => [ContactFieldRequirement::Optional, false],
    'required' => [ContactFieldRequirement::Required, true],
]);

it('never requires a field it does not collect', function (ContactFieldRequirement $requirement) {
    expect(! $requirement->isRequired() || $requirement->isCollected())->toBeTrue();
})->with(ContactFieldRequirement::cases());
