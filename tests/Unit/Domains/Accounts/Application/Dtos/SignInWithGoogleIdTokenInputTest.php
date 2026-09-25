<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Domains\Accounts\ValueObjects\SecondFactorProofKind;
use App\Shared\Contracts\DomainFailure;

it('resolves the credential the client posted', function () {
    expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature'])->idToken)
        ->toBe('eyJhbGciOiJSUzI1NiJ9.payload.signature');
});

it('carries the credential unverified and unaltered', function () {
    expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => ' not.a.token '])->idToken)
        ->toBe(' not.a.token ');
});

it('ignores the identity the client claimed alongside it', function () {
    $input = SignInWithGoogleIdTokenInput::fromRequest([
        'id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
        'email' => 'ada@example.com',
        'email_verified' => true,
        'sub' => '104729183746501928374',
    ]);

    expect($input)->toEqual(SignInWithGoogleIdTokenInput::fromRequest([
        'id_token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
    ]));
});

describe('validating the credential', function () {
    it('accepts any non blank credential, because checking it is the verifier job', function (string $idToken) {
        expect(fn () => (new SignInWithGoogleIdTokenInput($idToken))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a well formed token' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
        'a token the verifier will reject' => 'nonsense',
        'a single character' => 'a',
        'padded' => '  a.valid.token  ',
    ]);

    it('refuses a blank credential', function (string $idToken) {
        expect(fn () => (new SignInWithGoogleIdTokenInput($idToken))->validate())
            ->toThrow(InvalidGoogleIdToken::class, 'The supplied credential is not a Google ID token.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'a newline' => "\n",
    ]);

    it('refuses a payload carrying no credential as a domain failure, not a PHP error', function () {
        $thrown = null;

        try {
            SignInWithGoogleIdTokenInput::fromRequest([])->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidGoogleIdToken::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class);
    });
});

it('reads a credential that is not a string as absent, and refuses it as a credential', function (mixed $idToken) {
    expect(fn () => SignInWithGoogleIdTokenInput::fromRequest(['id_token' => $idToken])->validate())
        ->toThrow(InvalidGoogleIdToken::class, 'The supplied credential is not a Google ID token.');
})->with([
    'an array' => [['eyJhbGciOiJSUzI1NiJ9.payload.signature']],
    'the decoded claims the client should not be sending' => [['sub' => '104729183746501928374']],
    'a number' => 42,
    'a boolean' => true,
    'null' => null,
]);

describe('reading the second factor from the payload', function () {
    it('reads neither proof from a payload that carries only the credential', function () {
        $input = SignInWithGoogleIdTokenInput::fromRequest(['id_token' => 'a.valid.token']);

        expect($input->code)->toBeNull()
            ->and($input->recoveryCode)->toBeNull()
            ->and($input->secondFactorProof())->toBeNull();
    });

    it('reads the authenticator code, trimmed', function () {
        expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => 'a.valid.token', 'code' => ' 123456 '])->code)
            ->toBe('123456');
    });

    it('reads the recovery code, trimmed and otherwise untouched', function () {
        expect(SignInWithGoogleIdTokenInput::fromRequest(['id_token' => 'a.valid.token', 'recovery_code' => "\tAbCdE12345-fGhIj67890 "])->recoveryCode)
            ->toBe('AbCdE12345-fGhIj67890');
    });

    it('reads a blank or non string proof as absent', function (mixed $value) {
        $input = SignInWithGoogleIdTokenInput::fromRequest([
            'id_token' => 'a.valid.token',
            'code' => $value,
            'recovery_code' => $value,
        ]);

        expect($input->code)->toBeNull()
            ->and($input->recoveryCode)->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a newline' => "\n",
        'null' => null,
        'a number' => 123456,
        'a boolean' => true,
        'an array' => [['123456']],
    ]);
});

describe('validating the second factor', function () {
    it('accepts a payload with at most one proof inside its limit', function (array $payload) {
        expect(fn () => SignInWithGoogleIdTokenInput::fromRequest($payload)->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'no proof' => [['id_token' => 'a.valid.token']],
        'a code' => [['id_token' => 'a.valid.token', 'code' => '123456']],
        'a recovery code' => [['id_token' => 'a.valid.token', 'recovery_code' => 'ABCDE12345-FGHIJ67890']],
        'a code exactly at the limit' => [['id_token' => 'a.valid.token', 'code' => str_repeat('1', SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH)]],
        'a recovery code exactly at the limit' => [['id_token' => 'a.valid.token', 'recovery_code' => str_repeat('a', SignInWithGoogleIdTokenInput::MAXIMUM_RECOVERY_CODE_LENGTH)]],
        'a padded code whose trimmed length fits' => [['id_token' => 'a.valid.token', 'code' => str_repeat(' ', 20).'123456'.str_repeat(' ', 20)]],
        'a code of accented characters at the limit' => [['id_token' => 'a.valid.token', 'code' => str_repeat('é', SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH)]],
        'a blank code beside a recovery code' => [['id_token' => 'a.valid.token', 'code' => '  ', 'recovery_code' => 'ABCDE12345-FGHIJ67890']],
    ]);

    it('refuses a malformed second factor as a domain failure', function (array $payload, string $exception, string $message) {
        $thrown = null;

        try {
            SignInWithGoogleIdTokenInput::fromRequest($payload)->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf($exception)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->getMessage())->toBe($message);
    })->with([
        'both proofs at once' => [
            ['id_token' => 'a.valid.token', 'code' => '123456', 'recovery_code' => 'ABCDE12345-FGHIJ67890'],
            InvalidTwoFactorCode::class,
            'A two factor code and a recovery code cannot be given together.',
        ],
        'a code one past the limit' => [
            ['id_token' => 'a.valid.token', 'code' => str_repeat('1', SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH + 1)],
            InvalidTwoFactorCode::class,
            'The two factor code exceeds 16 characters.',
        ],
        'a code of accented characters one past the limit' => [
            ['id_token' => 'a.valid.token', 'code' => str_repeat('é', SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH + 1)],
            InvalidTwoFactorCode::class,
            'The two factor code exceeds 16 characters.',
        ],
        'a recovery code one past the limit' => [
            ['id_token' => 'a.valid.token', 'recovery_code' => str_repeat('a', SignInWithGoogleIdTokenInput::MAXIMUM_RECOVERY_CODE_LENGTH + 1)],
            InvalidRecoveryCode::class,
            'The recovery code exceeds 64 characters.',
        ],
    ]);

    it('refuses both proofs with the same failure its conflictingProofs factory builds', function () {
        $thrown = null;

        try {
            (new SignInWithGoogleIdTokenInput('a.valid.token', '123456', 'ABCDE12345-FGHIJ67890'))->validate();
        } catch (InvalidTwoFactorCode $failure) {
            $thrown = $failure;
        }

        expect($thrown?->getMessage())->toBe(InvalidTwoFactorCode::conflictingProofs()->getMessage())
            ->and($thrown?->errorCode())->toBe('invalid_two_factor_code');
    });

    it('refuses an overlong code with the same failure its tooLong factory builds', function () {
        $thrown = null;

        try {
            (new SignInWithGoogleIdTokenInput('a.valid.token', str_repeat('1', 17)))->validate();
        } catch (InvalidTwoFactorCode $failure) {
            $thrown = $failure;
        }

        expect($thrown?->getMessage())
            ->toBe(InvalidTwoFactorCode::tooLong(SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH)->getMessage());
    });
});

describe('the second factor proof it hands the use case', function () {
    it('offers none when the payload carried none', function () {
        expect((new SignInWithGoogleIdTokenInput('a.valid.token'))->secondFactorProof())->toBeNull();
    });

    it('offers the code as an authenticator proof', function () {
        $proof = (new SignInWithGoogleIdTokenInput('a.valid.token', code: '123456'))->secondFactorProof();

        expect($proof?->kind())->toBe(SecondFactorProofKind::Totp)
            ->and($proof?->value())->toBe('123456');
    });

    it('offers the recovery code as a recovery proof', function () {
        $proof = (new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: 'ABCDE12345-FGHIJ67890'))->secondFactorProof();

        expect($proof?->kind())->toBe(SecondFactorProofKind::RecoveryCode)
            ->and($proof?->value())->toBe('ABCDE12345-FGHIJ67890');
    });
});
