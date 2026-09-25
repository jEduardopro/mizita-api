<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Resources\PasskeyRegistrationResource;
use Tests\Support\Accounts\SignInSecurityFixtures;
use Tests\TestCase;

uses(TestCase::class);

it('serializes the registered passkey as its uuid and its name alone, wrapped in data', function () {
    expect(PasskeyRegistrationResource::make(SignInSecurityFixtures::passkeyData())->response()->getData(true))
        ->toBe(['data' => [
            'id' => SignInSecurityFixtures::PASSKEY_ID,
            'name' => SignInSecurityFixtures::PASSKEY_NAME,
        ]]);
});

it('identifies the passkey by its uuid', function () {
    expect(PasskeyRegistrationResource::make(SignInSecurityFixtures::passkeyData())->response()->getData(true)['data']['id'])
        ->toBeUuid();
});
