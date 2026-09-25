<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Auth\DiscardTemporaryPasswordOnLogin;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\Support\Accounts\FakeTemporaryPasswordVault;
use Tests\Support\Accounts\InvitationFixtures;

beforeEach(function () {
    $this->vault = (new FakeTemporaryPasswordVault)->holds(InvitationFixtures::EXISTING_ACCOUNT_ID, InvitationFixtures::TEMPORARY_PASSWORD);
    $this->listener = new DiscardTemporaryPasswordOnLogin($this->vault);
});

it('discards the temporary password of the account that just signed in, by its uuid', function (bool $remember) {
    $account = (new User)->forceFill(['id' => 7, 'uuid' => InvitationFixtures::EXISTING_ACCOUNT_ID]);

    $this->listener->handle(new Login('web', $account, $remember));

    expect($this->vault->discarded)->toBe([InvitationFixtures::EXISTING_ACCOUNT_ID])
        ->and($this->vault->reveal(InvitationFixtures::EXISTING_ACCOUNT_ID))->toBeNull();
})->with([
    'remembered' => true,
    'not remembered' => false,
]);

it('leaves the vault alone when whoever signed in is not an account', function () {
    $this->listener->handle(new Login('web', Mockery::mock(Authenticatable::class), false));

    expect($this->vault->discarded)->toBe([])
        ->and($this->vault->reveal(InvitationFixtures::EXISTING_ACCOUNT_ID))->toBe(InvitationFixtures::TEMPORARY_PASSWORD);
});
