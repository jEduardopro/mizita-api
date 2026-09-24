<?php

declare(strict_types=1);

use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\ValueObjects\JobTitle;
use App\Shared\Contracts\DomainFailure;

it('holds a job title trimmed of surrounding whitespace', function () {
    expect(JobTitle::fromNullable("  Barbera principal\n")?->value)->toBe('Barbera principal');
});

it('reads nothing worth keeping as no job title at all', function (?string $value) {
    expect(JobTitle::fromNullable($value))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'spaces' => '   ',
    'tab and newline' => "\t\n",
]);

it('keeps accents and non-latin characters intact', function (string $value) {
    expect(JobTitle::fromNullable($value)?->value)->toBe($value);
})->with([
    'accents' => 'Peluquera y maquilladora señor',
    'cjk' => '美容師',
]);

it('accepts a job title at exactly the limit, counting characters rather than bytes', function (string $character) {
    $value = str_repeat($character, JobTitle::MAXIMUM_LENGTH);

    expect(JobTitle::fromNullable($value)?->value)->toBe($value);
})->with([
    'ascii' => 'a',
    'accented' => 'é',
]);

it('measures the job title after trimming it', function () {
    $value = '  '.str_repeat('a', JobTitle::MAXIMUM_LENGTH).'  ';

    expect(JobTitle::fromNullable($value)?->value)->toBe(str_repeat('a', JobTitle::MAXIMUM_LENGTH));
});

it('rejects a job title one character past the limit, naming the limit', function () {
    expect(fn () => JobTitle::fromNullable(str_repeat('a', JobTitle::MAXIMUM_LENGTH + 1)))
        ->toThrow(InvalidProfileJobTitle::class, 'A job title takes up to [120] characters.');
});

it('refuses through a domain failure the renderer understands', function () {
    try {
        JobTitle::fromNullable(str_repeat('a', JobTitle::MAXIMUM_LENGTH + 1));
        $thrown = null;
    } catch (Throwable $failure) {
        $thrown = $failure;
    }

    expect($thrown)->toBeInstanceOf(DomainFailure::class);
});

it('restores a stored value untouched, skipping the length and trimming rules', function (string $value) {
    expect(JobTitle::restore($value)->value)->toBe($value);
})->with([
    'untrimmed' => '  Barbera  ',
    'past the limit' => str_repeat('a', JobTitle::MAXIMUM_LENGTH + 5),
]);
