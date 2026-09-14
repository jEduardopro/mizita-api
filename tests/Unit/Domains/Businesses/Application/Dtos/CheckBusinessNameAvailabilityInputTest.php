<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Shared\Contracts\DomainFailure;

it('resolves the name the caller asked about', function () {
    expect(CheckBusinessNameAvailabilityInput::fromRequest(['name' => 'Barbería Ñandú'])->name)
        ->toBe('Barbería Ñandú');
});

it('hands the name over untouched, because the use case is what trims it', function () {
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

describe('validating the name the caller asked about', function () {
    it('accepts a name inside the bounds the form request also enforces', function (string $name) {
        expect(fn () => (new CheckBusinessNameAvailabilityInput($name))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'the shortest name allowed' => 'Bo',
        'the longest name allowed' => str_repeat('a', 120),
        'accented' => 'Barbería Ñandú',
        'padded but long enough once trimmed' => '  Bo  ',
        'a name that yields no address, which is the use case verdict not this one' => '北京 沙龙',
    ]);

    it('refuses a name outside them', function (string $name) {
        expect(fn () => (new CheckBusinessNameAvailabilityInput($name))->validate())
            ->toThrow(InvalidBusinessName::class);
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'tab' => "\t",
        'one character' => 'B',
        'one character once trimmed' => '  B  ',
        'one character beyond the maximum' => str_repeat('a', 121),
    ]);

    it('tells an empty name apart from a short one, because the advice differs', function () {
        expect(fn () => (new CheckBusinessNameAvailabilityInput('   '))->validate())
            ->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
    });

    it('names the short name it refused', function () {
        expect(fn () => (new CheckBusinessNameAvailabilityInput('B'))->validate())
            ->toThrow(InvalidBusinessName::class, '[B] is too short for a business name.');
    });

    it('counts the bound in characters, not in bytes', function () {
        expect(fn () => (new CheckBusinessNameAvailabilityInput(str_repeat('ñ', 120)))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a payload with no name at all as a domain failure, not a PHP error', function () {
        $thrown = null;

        try {
            CheckBusinessNameAvailabilityInput::fromRequest([])->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidBusinessName::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    });
});

it('reads a name that is not a string as absent, and refuses it as a name', function (mixed $name) {
    expect(fn () => CheckBusinessNameAvailabilityInput::fromRequest(['name' => $name])->validate())
        ->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
})->with([
    'an array' => [['Barbería Ñandú']],
    'a number' => 42,
    'a boolean' => true,
    'null' => null,
]);
