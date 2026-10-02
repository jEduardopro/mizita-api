<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\EloquentAccountPasskeys;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

const ACCOUNT_PASSKEYS_OWNER_UUID = '01930000-0000-7000-8000-0000000000e1';

beforeEach(function () {
    $this->issued = DB::pretend(fn () => (new EloquentAccountPasskeys)->deleteAllOf(ACCOUNT_PASSKEYS_OWNER_UUID));
    $this->statement = mb_strtolower($this->issued[0]['query'] ?? '');
});

it('issues a single statement, whatever the number of passkeys', function () {
    expect($this->issued)->toHaveCount(1);
});

it('soft deletes the passkeys rather than erasing the rows', function () {
    expect($this->statement)->toStartWith('update "passkeys" set "deleted_at" = ')
        ->not->toContain('delete from');
});

it('reaches the passkeys through the account uuid, never a guessed int key', function () {
    expect($this->statement)
        ->toContain('"user_id" in (select "id" from "users" where "uuid" = \''.ACCOUNT_PASSKEYS_OWNER_UUID.'\')');
});

it('still finds the account when it is scheduled for deletion', function () {
    expect($this->statement)->not->toContain('"users"."deleted_at" is null');
});

it('leaves an already revoked passkey with its original deletion instant', function () {
    expect($this->statement)->toContain('"passkeys"."deleted_at" is null');
});
