<?php

declare(strict_types=1);

use App\Domains\Phones\ValueObjects\PhoneNumberFragment;

it('keeps only the digits of what was typed', function (string $typed, string $digits) {
    expect(PhoneNumberFragment::of($typed)?->digits)->toBe($digits);
})->with([
    'plain digits' => ['5512', '5512'],
    'a full international number' => ['+52 55 1234 5678', '525512345678'],
    'parentheses and dashes' => ['(55) 12-34', '551234'],
    'padded with spaces' => ['  5512  ', '5512'],
    'digits among letters' => ['tel 55 12', '5512'],
    'a tab and a newline' => ["55\t1\n2", '5512'],
]);

it('accepts a fragment of exactly four digits', function () {
    $fragment = PhoneNumberFragment::of('7777');

    expect($fragment)->toBeInstanceOf(PhoneNumberFragment::class)
        ->and($fragment->digits)->toBe('7777')
        ->and(strlen($fragment->digits))->toBe(PhoneNumberFragment::MINIMUM_DIGITS);
});

it('refuses to search on fewer than four digits, because they would match nearly every number', function (?string $typed) {
    expect(PhoneNumberFragment::of($typed))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'whitespace only' => '   ',
    'letters only' => 'Ada Lovelace',
    'punctuation only' => '+- ()',
    'one digit' => '5',
    'three digits' => '551',
    'three digits spread by punctuation' => '+5-5 1',
    'three digits among letters' => 'Ada 551',
]);
