<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

uses(TestCase::class);

const PASSKEY_MODEL_UUID = '01930000-0000-7000-8000-0000000000d1';

function signedInPasskeyOwner(): User
{
    $owner = (new User)->forceFill(['id' => 7, 'uuid' => '01930000-0000-7000-8000-0000000000d2']);

    Auth::guard('web')->setUser($owner);

    return $owner;
}

/**
 * @return list<string>
 */
function queriesIssuedWhile(callable $action): array
{
    $issued = [];

    DB::listen(function ($query) use (&$issued) {
        $issued[] = $query->sql;
    });

    $action();

    return $issued;
}

describe('its identity', function () {
    it('lives in the passkeys table', function () {
        expect((new PasskeyModel)->getTable())->toBe('passkeys');
    });

    it('generates the uuid column and nothing else', function () {
        expect((new PasskeyModel)->uniqueIds())->toBe(['uuid']);
    });

    it('keeps the int primary key auto incrementing', function () {
        $passkey = new PasskeyModel;

        expect($passkey->getIncrementing())->toBeTrue()
            ->and($passkey->getKeyType())->toBe('int');
    });

    it('is routed by its uuid, never by the int key', function () {
        expect((new PasskeyModel)->getRouteKeyName())->toBe('uuid');
    });
});

describe('its record lifecycle', function () {
    it('hides soft deleted rows from every query by default', function () {
        new PasskeyModel;

        expect(PasskeyModel::hasGlobalScope(SoftDeletingScope::class))->toBeTrue();
    });

    it('reports a row carrying a deletion instant as trashed', function () {
        expect((new PasskeyModel)->forceFill(['deleted_at' => '2026-09-01 10:00:00'])->trashed())->toBeTrue();
    });
});

describe('its owner', function () {
    it('belongs to the application account through user_id', function () {
        $owner = (new PasskeyModel)->user();

        expect($owner->getRelated())->toBeInstanceOf(User::class)
            ->and($owner->getForeignKeyName())->toBe('user_id');
    });

    it('still reads an owner that was soft deleted', function () {
        expect((new PasskeyModel)->user()->getQuery()->removedScopes())->toContain(SoftDeletingScope::class);
    });

    it('overrides a vendor relation that would have hidden a soft deleted owner', function () {
        expect((new Passkey)->user()->getQuery()->removedScopes())->not->toContain(SoftDeletingScope::class);
    });

    it('reads the owner key back as an int', function () {
        expect((new PasskeyModel)->forceFill(['user_id' => '42'])->user_id)->toBe(42);
    });

    it('keeps the vendor casts, so the stored credential still decodes', function () {
        expect((new PasskeyModel)->getCasts())
            ->toHaveKey('credential', 'json')
            ->toHaveKey('last_used_at', 'datetime')
            ->toHaveKey('user_id', 'integer');
    });
});

describe('route model binding', function () {
    it('resolves nothing for a guest, without touching the database', function () {
        $resolved = PASSKEY_MODEL_UUID;

        $issued = queriesIssuedWhile(function () use (&$resolved) {
            $resolved = (new PasskeyModel)->resolveRouteBinding(PASSKEY_MODEL_UUID);
        });

        expect($resolved)->toBeNull()
            ->and($issued)->toBe([]);
    });

    it('resolves nothing for a key that is not a uuid, without touching the database', function (mixed $key) {
        signedInPasskeyOwner();
        $resolved = PASSKEY_MODEL_UUID;

        $issued = queriesIssuedWhile(function () use (&$resolved, $key) {
            $resolved = (new PasskeyModel)->resolveRouteBinding($key);
        });

        expect($resolved)->toBeNull()
            ->and($issued)->toBe([]);
    })->with([
        'an int key' => [42],
        'a numeric string' => ['42'],
        'an empty string' => [''],
        'free text' => ['my-laptop'],
        'a uuid with trailing junk' => [PASSKEY_MODEL_UUID.'x'],
    ]);
});
