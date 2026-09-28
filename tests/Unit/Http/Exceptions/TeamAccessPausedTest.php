<?php

declare(strict_types=1);

use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Exceptions\TeamAccessPaused;
use App\Http\Responses\FailurePayload;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

const PAUSED_BUSINESS_UUID = '01930000-0000-7000-8000-00000000ca5e';

beforeEach(function () {
    app()->setLocale('en');
});

function pausedOnTheWire(TeamAccessPaused $failure): array
{
    $response = (new RenderDomainFailure)($failure, Request::create('/api/services', 'GET'));

    return json_decode($response->getContent(), associative: true);
}

dataset('team access paused failures', [
    'every membership' => fn () => TeamAccessPaused::forEveryMembership(),
    'one business' => fn () => TeamAccessPaused::forBusiness(PAUSED_BUSINESS_UUID),
]);

it('names itself team_access_paused', function (TeamAccessPaused $failure) {
    expect($failure->errorCode())->toBe('team_access_paused');
})->with('team access paused failures');

it('is forbidden, so the api answers 403', function (TeamAccessPaused $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Forbidden)
        ->and(FailurePayload::for($failure->errorCode(), $failure->kind())->status)->toBe(403);
})->with('team access paused failures');

it('renders the flat message and code body the client expects', function (TeamAccessPaused $failure) {
    expect(pausedOnTheWire($failure))->toBe([
        'message' => 'Your access to this business is paused because its current plan does not include a team.',
        'code' => 'team_access_paused',
    ]);
})->with('team access paused failures');

it('answers in spanish when the request resolved spanish', function () {
    app()->setLocale('es');

    expect(pausedOnTheWire(TeamAccessPaused::forEveryMembership())['message'])
        ->toBe('Tu acceso a este negocio está en pausa porque su plan actual no incluye equipo.');
});

it('keeps the paused business in the developer message, for the log and nowhere else', function () {
    expect(TeamAccessPaused::forBusiness(PAUSED_BUSINESS_UUID)->getMessage())->toContain(PAUSED_BUSINESS_UUID);
});

it('keeps the paused business uuid out of the rendered body in every locale we ship', function (string $locale) {
    app()->setLocale($locale);

    expect(json_encode(pausedOnTheWire(TeamAccessPaused::forBusiness(PAUSED_BUSINESS_UUID))))
        ->not->toContain(PAUSED_BUSINESS_UUID);
})->with(['en', 'es']);

it('is a throwable domain failure, so the handler can both catch and classify it', function () {
    expect(TeamAccessPaused::forEveryMembership())->toBeInstanceOf(DomainFailure::class)
        ->and(TeamAccessPaused::forEveryMembership())->toBeInstanceOf(RuntimeException::class);
});
