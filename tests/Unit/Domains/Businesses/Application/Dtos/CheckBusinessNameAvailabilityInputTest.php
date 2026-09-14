<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;

it('resolves the name the caller asked about', function () {
    expect(CheckBusinessNameAvailabilityInput::fromRequest(['name' => 'Barbería Ñandú'])->name)
        ->toBe('Barbería Ñandú');
});

it('hands the name over untouched, because the use case is what trims it', function () {
    // Trimming twice is one place too many to keep in step: the availability
    // answer has to be computed from the same string the signup later writes.
    expect(CheckBusinessNameAvailabilityInput::fromRequest(['name' => '   Barbería Ñandú   '])->name)
        ->toBe('   Barbería Ñandú   ');
});

it('ignores anything else the payload happens to carry', function () {
    $input = CheckBusinessNameAvailabilityInput::fromRequest([
        'name' => 'Barbería Ñandú',
        'slug' => 'a-slug-i-picked',
    ]);

    expect($input)->toEqual(CheckBusinessNameAvailabilityInput::fromRequest(['name' => 'Barbería Ñandú']));
});
