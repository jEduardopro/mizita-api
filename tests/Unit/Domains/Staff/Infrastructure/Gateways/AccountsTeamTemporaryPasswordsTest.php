<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Gateways\AccountsTeamTemporaryPasswords;
use Tests\Support\Accounts\FakeTemporaryPasswordVault;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->vault = (new FakeTemporaryPasswordVault)->holds(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);
    $this->gateway = new AccountsTeamTemporaryPasswords($this->vault);
});

it('reveals the temporary password the account still holds', function () {
    expect($this->gateway->revealFor(StaffFixtures::SECOND_ACCOUNT_ID))->toBe(StaffFixtures::TEMPORARY_PASSWORD);
});

it('reveals nothing for an account that holds none', function () {
    expect($this->gateway->revealFor(StaffFixtures::ACCOUNT_ID))->toBeNull();
});

it('answers which of the accounts asked about still hold one, in a single batch', function () {
    expect($this->gateway->availableAmong([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]))
        ->toBe([StaffFixtures::SECOND_ACCOUNT_ID])
        ->and($this->vault->batchReads)->toBe([[StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]]);
});

it('never discards the password it reveals', function () {
    $this->gateway->revealFor(StaffFixtures::SECOND_ACCOUNT_ID);

    expect($this->vault->discarded)->toBe([])
        ->and($this->gateway->revealFor(StaffFixtures::SECOND_ACCOUNT_ID))->toBe(StaffFixtures::TEMPORARY_PASSWORD);
});
