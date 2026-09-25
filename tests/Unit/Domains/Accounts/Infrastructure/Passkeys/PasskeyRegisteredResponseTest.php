<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\PasskeyMapper;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Domains\Accounts\Infrastructure\Passkeys\PasskeyRegisteredResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Passkey;
use Tests\Support\Accounts\SignInSecurityFixtures;
use Tests\TestCase;

uses(TestCase::class);

function registeredPasskeyModel(): PasskeyModel
{
    return (new PasskeyModel)->setRawAttributes([
        'id' => 42,
        'uuid' => SignInSecurityFixtures::PASSKEY_ID,
        'user_id' => 7,
        'name' => SignInSecurityFixtures::PASSKEY_NAME,
        'credential_id' => 'credential-id',
        'credential' => json_encode(['aaguid' => 'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4', 'publicKey' => 'secret-key-material']),
        'created_at' => new DateTimeImmutable(SignInSecurityFixtures::CREATED_AT),
        'last_used_at' => null,
    ], sync: true);
}

function passkeyRegistrationRequest(string $accept): Request
{
    return Request::create('/user/passkeys', 'POST', server: [
        'HTTP_ACCEPT' => $accept,
        'HTTP_REFERER' => 'https://mizita.test/profile/security',
    ]);
}

beforeEach(function () {
    $this->response = new PasskeyRegisteredResponse(new PasskeyMapper);
});

it('is what the container hands the passkeys package, so the vendor response never answers with the int id', function () {
    expect(app(PasskeyRegistrationResponse::class))->toBeInstanceOf(PasskeyRegisteredResponse::class);
});

describe('a json request', function () {
    it('answers 201 created', function () {
        $response = $this->response->withPasskey(registeredPasskeyModel())
            ->toResponse(passkeyRegistrationRequest('application/json'));

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(201);
    });

    it('answers with the passkey uuid and name wrapped in data, and nothing else', function () {
        $response = $this->response->withPasskey(registeredPasskeyModel())
            ->toResponse(passkeyRegistrationRequest('application/json'));

        expect($response->getData(true))->toBe(['data' => [
            'id' => SignInSecurityFixtures::PASSKEY_ID,
            'name' => SignInSecurityFixtures::PASSKEY_NAME,
        ]]);
    });

    it('leaks neither the int key, the owner key nor the credential', function () {
        $body = $this->response->withPasskey(registeredPasskeyModel())
            ->toResponse(passkeyRegistrationRequest('application/json'))
            ->getContent();

        expect($body)->not->toContain('42')
            ->not->toContain('user_id')
            ->not->toContain('credential')
            ->not->toContain('secret-key-material');
    });

    it('refuses to render when no passkey was handed over first', function () {
        expect(fn () => $this->response->toResponse(passkeyRegistrationRequest('application/json')))
            ->toThrow(LogicException::class, 'No passkey was registered before rendering the registration response.');
    });
});

describe('a browser form post', function () {
    it('redirects back with the registered status flashed', function () {
        $request = passkeyRegistrationRequest('text/html');
        app()->instance('request', $request);

        $response = $this->response->withPasskey(registeredPasskeyModel())->toResponse($request);

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getStatusCode())->toBe(302)
            ->and($response->getTargetUrl())->toBe('https://mizita.test/profile/security')
            ->and($response->getSession()->get('status'))->toBe('passkey-registered');
    });
});

it('refuses a passkey stored through any model but its own, which carries no uuid', function () {
    expect(fn () => $this->response->withPasskey(new Passkey))
        ->toThrow(LogicException::class, 'Passkeys must be stored through '.PasskeyModel::class.'.');
});
