<?php

declare(strict_types=1);

use App\Domains\Services\ValueObjects\ServiceColor;

it('offers exactly the nine colours the palette carries', function () {
    expect(array_map(
        static fn (ServiceColor $color): string => $color->value,
        ServiceColor::cases(),
    ))->toBe(['red', 'orange', 'amber', 'purple', 'blue', 'sand', 'slate', 'teal', 'green']);
});

it('reads a colour the client sent', function (string $value) {
    expect(ServiceColor::tryFrom($value))->toBeInstanceOf(ServiceColor::class)
        ->and(ServiceColor::from($value)->value)->toBe($value);
})->with(['red', 'sand', 'slate', 'green']);

it('refuses a colour outside the palette rather than guessing one', function (string $value) {
    expect(ServiceColor::tryFrom($value))->toBeNull();
})->with([
    'a hex value' => '#ff0000',
    'a tailwind name' => 'rose',
    'uppercase' => 'RED',
    'padded' => ' red ',
    'empty' => '',
]);
