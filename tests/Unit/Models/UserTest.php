<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class);

const USER_TEMPORARY_PASSWORD = 'Tq7mW2xK9pLr4ZvB8nYd';

describe('the temporary password an owner may copy', function () {
    it('holds it encrypted, never as the plaintext', function () {
        $account = (new User)->forceFill(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        $stored = $account->getAttributes()['temporary_password'];

        expect($stored)->toBeString()
            ->and($stored)->not->toBe(USER_TEMPORARY_PASSWORD)
            ->and($stored)->not->toContain(USER_TEMPORARY_PASSWORD)
            ->and(Crypt::decryptString($stored))->toBe(USER_TEMPORARY_PASSWORD);
    });

    it('reads it back decrypted', function () {
        $account = (new User)->forceFill(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        expect($account->temporary_password)->toBe(USER_TEMPORARY_PASSWORD);
    });

    it('stores a discarded one as null rather than as an encrypted empty value', function () {
        $account = (new User)->forceFill(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        $account->forceFill(['temporary_password' => null]);

        expect($account->getAttributes()['temporary_password'])->toBeNull()
            ->and($account->temporary_password)->toBeNull();
    });

    it('never serializes it, encrypted or not', function () {
        $account = (new User)->forceFill([
            'uuid' => '01930000-0000-7000-8000-0000000000c2',
            'temporary_password' => USER_TEMPORARY_PASSWORD,
        ]);

        expect($account->toArray())->not->toHaveKey('temporary_password')
            ->and($account->toJson())->not->toContain(USER_TEMPORARY_PASSWORD)
            ->and($account->toJson())->not->toContain($account->getAttributes()['temporary_password']);
    });

    it('cannot be mass assigned from a payload', function () {
        $account = new User(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        expect($account->getAttributes())->not->toHaveKey('temporary_password');
    });
});
