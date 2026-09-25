<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\AuthorizationUrlData;
use App\Domains\Integrations\Infrastructure\Http\Resources\AuthorizationUrlResource;
use Tests\TestCase;

uses(TestCase::class);

it('serializes exactly the consent url, inside the data envelope', function () {
    $url = 'https://accounts.google.com/o/oauth2/auth?state=abc&scope=openid+email';

    expect(AuthorizationUrlResource::make(new AuthorizationUrlData($url))->response()->getData(true))
        ->toBe(['data' => ['authorization_url' => $url]]);
});
