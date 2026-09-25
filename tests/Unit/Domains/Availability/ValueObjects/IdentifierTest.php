<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\Identifier;

it('recognises the uuid shape the api speaks in', function (string $value) {
    expect(Identifier::isWellFormed($value))->toBeTrue();
})->with([
    'a time ordered uuid' => '01930000-0000-7000-8000-000000000001',
    'a random uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    'uppercase' => 'F47AC10B-58CC-4372-A567-0E02B2C3D479',
    'mixed case' => 'F47ac10B-58cc-4372-A567-0e02B2c3d479',
    'all zeroes' => '00000000-0000-0000-0000-000000000000',
    'all fs' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
]);

it('turns down anything that is not that shape', function (string $value) {
    expect(Identifier::isWellFormed($value))->toBeFalse();
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a row number' => '1',
    'a word' => 'not-a-uuid',
    'no dashes' => 'f47ac10b58cc4372a5670e02b2c3d479',
    'dashes in the wrong places' => 'f47ac10b5-8cc-4372-a567-0e02b2c3d479',
    'a non hexadecimal character' => 'g47ac10b-58cc-4372-a567-0e02b2c3d479',
    'one character short' => 'f47ac10b-58cc-4372-a567-0e02b2c3d47',
    'one character too long' => 'f47ac10b-58cc-4372-a567-0e02b2c3d4790',
    'surrounding whitespace' => ' f47ac10b-58cc-4372-a567-0e02b2c3d479 ',
    'wrapped in braces' => '{f47ac10b-58cc-4372-a567-0e02b2c3d479}',
    'a urn prefix' => 'urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479',
    'something appended' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479/../admin',
    'unicode digits' => '０1930000-0000-7000-8000-000000000001',
]);

it('refuses an identifier smuggling a second line past the end', function () {
    expect(Identifier::isWellFormed("f47ac10b-58cc-4372-a567-0e02b2c3d479\n"))->toBeFalse()
        ->and(Identifier::isWellFormed("f47ac10b-58cc-4372-a567-0e02b2c3d479\nDROP"))->toBeFalse();
});
