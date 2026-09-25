<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\PasswordChangeRequired;
use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Responses\FailurePayload;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

it('names itself password_change_required', function () {
    expect(PasswordChangeRequired::beforeContinuing()->errorCode())->toBe('password_change_required');
});

it('is forbidden, so the api answers 403', function () {
    $failure = PasswordChangeRequired::beforeContinuing();

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure->kind())->toBe(DomainFailureKind::Forbidden)
        ->and(FailurePayload::for($failure->errorCode(), $failure->kind())->status)->toBe(403);
});

it('renders the flat message and code body the client already knows how to show', function (string $locale, string $message) {
    app()->setLocale($locale);

    $response = (new RenderDomainFailure)(
        PasswordChangeRequired::beforeContinuing(),
        Request::create('/api/services', 'GET'),
    );

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), associative: true))->toBe([
            'message' => $message,
            'code' => 'password_change_required',
        ]);
})->with([
    'en' => ['en', 'You need to set a new password before continuing.'],
    'es' => ['es', 'Necesitas establecer una nueva contraseña antes de continuar.'],
]);
