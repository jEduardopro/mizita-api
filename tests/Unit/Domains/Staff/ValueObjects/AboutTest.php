<?php

declare(strict_types=1);

use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\ValueObjects\About;
use App\Shared\Contracts\DomainFailure;

it('holds a description trimmed of surrounding whitespace', function () {
    expect(About::fromNullable("\n  Diez años cortando el pelo.  ")?->value)->toBe('Diez años cortando el pelo.');
});

it('keeps the line breaks inside a description', function () {
    expect(About::fromNullable("Primera línea.\nSegunda línea.")?->value)->toBe("Primera línea.\nSegunda línea.");
});

it('reads nothing worth keeping as no description at all', function (?string $value) {
    expect(About::fromNullable($value))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'spaces' => '   ',
    'blank lines' => "\n\n\t",
]);

it('accepts a description at exactly the limit, counting characters rather than bytes', function (string $character) {
    $value = str_repeat($character, About::MAXIMUM_LENGTH);

    expect(About::fromNullable($value)?->value)->toBe($value);
})->with([
    'ascii' => 'a',
    'accented' => 'ñ',
    'emoji' => '✂',
]);

it('rejects a description one character past the limit, naming the limit', function () {
    expect(fn () => About::fromNullable(str_repeat('a', About::MAXIMUM_LENGTH + 1)))
        ->toThrow(InvalidProfileAbout::class, 'A profile description takes up to [1000] characters.');
});

it('refuses through a domain failure the renderer understands', function () {
    try {
        About::fromNullable(str_repeat('a', About::MAXIMUM_LENGTH + 1));
        $thrown = null;
    } catch (Throwable $failure) {
        $thrown = $failure;
    }

    expect($thrown)->toBeInstanceOf(DomainFailure::class);
});

it('restores a stored value untouched, skipping the length and trimming rules', function (string $value) {
    expect(About::restore($value)->value)->toBe($value);
})->with([
    'untrimmed' => '  Diez años  ',
    'past the limit' => str_repeat('a', About::MAXIMUM_LENGTH + 5),
]);
