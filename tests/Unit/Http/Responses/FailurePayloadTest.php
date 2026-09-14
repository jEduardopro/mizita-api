<?php

declare(strict_types=1);

use App\Http\Responses\FailurePayload;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    app()->setLocale('en');
});

it('maps every failure kind to the status the api answers with', function (DomainFailureKind $kind, int $status) {
    expect(FailurePayload::for('no_business', $kind)->status)->toBe($status);
})->with([
    'invalid' => [DomainFailureKind::Invalid, 422],
    'conflict' => [DomainFailureKind::Conflict, 409],
    'not found' => [DomainFailureKind::NotFound, 404],
    'unauthenticated' => [DomainFailureKind::Unauthenticated, 401],
    'forbidden' => [DomainFailureKind::Forbidden, 403],
]);

it('leaves no kind of the enum without a status', function () {
    foreach (DomainFailureKind::cases() as $kind) {
        expect(FailurePayload::for('no_business', $kind)->status)->toBeGreaterThanOrEqual(400);
    }
});

it('keeps the code it was given', function () {
    expect(FailurePayload::for('business_not_accessible', DomainFailureKind::Forbidden)->code)
        ->toBe('business_not_accessible');
});

it('translates the code into the message for that code', function () {
    expect(FailurePayload::for('no_business', DomainFailureKind::Forbidden)->message)
        ->toBe('This user does not belong to a business.');
});

it('translates the message in the locale the request resolved', function () {
    app()->setLocale('es');

    expect(FailurePayload::for('no_business', DomainFailureKind::Forbidden)->message)
        ->toBe('Este usuario no pertenece a ningún negocio.');
});

it('builds a body of exactly the message and the code', function () {
    expect(FailurePayload::for('no_business', DomainFailureKind::Forbidden)->body)->toBe([
        'message' => 'This user does not belong to a business.',
        'code' => 'no_business',
    ]);
});

describe('the server error', function () {
    it('answers 500', function () {
        expect(FailurePayload::serverError()->status)->toBe(500);
    });

    it('names itself server_error', function () {
        expect(FailurePayload::serverError()->code)->toBe('server_error');
    });

    it('builds a body of exactly the message and the code', function () {
        expect(FailurePayload::serverError()->body)->toBe([
            'message' => 'Something went wrong on our side. Please try again.',
            'code' => 'server_error',
        ]);
    });

    it('says nothing about what actually broke', function () {
        expect(FailurePayload::serverError()->message)
            ->not->toContain('Exception')
            ->not->toContain('SQL');
    });
});
