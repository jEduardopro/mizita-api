<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\SecondFactorProof;
use App\Domains\Accounts\ValueObjects\SecondFactorProofKind;

it('builds an authenticator proof from a code', function () {
    $proof = SecondFactorProof::totp('123456');

    expect($proof->kind())->toBe(SecondFactorProofKind::Totp)
        ->and($proof->value())->toBe('123456');
});

it('builds a recovery proof from a recovery code', function () {
    $proof = SecondFactorProof::recoveryCode('ABCDE12345-FGHIJ67890');

    expect($proof->kind())->toBe(SecondFactorProofKind::RecoveryCode)
        ->and($proof->value())->toBe('ABCDE12345-FGHIJ67890');
});

it('trims the surrounding whitespace off either kind of proof', function (Closure $build, string $raw, string $expected) {
    expect($build($raw)->value())->toBe($expected);
})->with([
    'a padded code' => [fn (string $code) => SecondFactorProof::totp($code), "  123456\n", '123456'],
    'a padded recovery code' => [fn (string $code) => SecondFactorProof::recoveryCode($code), "\tABCDE12345-FGHIJ67890 ", 'ABCDE12345-FGHIJ67890'],
]);

it('keeps the inside of a recovery code exactly as given, case and dashes included', function () {
    expect(SecondFactorProof::recoveryCode('aBcDe 12345-fGhIj')->value())->toBe('aBcDe 12345-fGhIj');
});

it('carries a blank code as empty, leaving the refusal to the verifier', function (Closure $build) {
    expect($build('   ')->value())->toBe('');
})->with([
    'authenticator' => fn (string $code) => SecondFactorProof::totp($code),
    'recovery' => fn (string $code) => SecondFactorProof::recoveryCode($code),
]);

it('tells the two kinds apart even when their values match', function () {
    expect(SecondFactorProof::totp('123456'))->not->toEqual(SecondFactorProof::recoveryCode('123456'));
});
