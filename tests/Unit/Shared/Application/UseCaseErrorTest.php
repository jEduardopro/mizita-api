<?php

declare(strict_types=1);

use App\Shared\Application\UseCaseError;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Unit\Shared\Application\FailingDomainRule;

describe('an error built from the exception a domain threw', function () {
    it('copies the classification the exception gave itself', function (DomainFailureKind $kind) {
        $error = UseCaseError::from(new FailingDomainRule('google_email_not_verified', $kind));

        expect($error->code)->toBe('google_email_not_verified')
            ->and($error->kind)->toBe($kind);
    })->with([
        'invalid' => DomainFailureKind::Invalid,
        'conflict' => DomainFailureKind::Conflict,
        'not found' => DomainFailureKind::NotFound,
        'unauthenticated' => DomainFailureKind::Unauthenticated,
        'forbidden' => DomainFailureKind::Forbidden,
    ]);

    it('keeps the exception itself, not a copy of its fields', function () {
        $failure = new FailingDomainRule;

        $error = UseCaseError::from($failure);

        expect($error->cause())->toBe($failure)
            ->and($error->toThrowable())->toBe($failure);
    });

    it('hands back the same instance every time it is asked to throw', function () {
        $error = UseCaseError::from(new FailingDomainRule);

        expect($error->toThrowable())->toBe($error->toThrowable());
    });
});

describe('an error a use case declared without an exception', function () {
    it('has no cause to point at', function () {
        expect(UseCaseError::of('unknown_industry', DomainFailureKind::NotFound)->cause())->toBeNull();
    });

    it('invents a UseCaseFailed carrying the same code and kind', function (DomainFailureKind $kind) {
        $thrown = UseCaseError::of('unknown_industry', $kind)->toThrowable();

        expect($thrown)->toBeInstanceOf(UseCaseFailed::class)
            ->and($thrown->errorCode())->toBe('unknown_industry')
            ->and($thrown->kind())->toBe($kind);
    })->with([
        'invalid' => DomainFailureKind::Invalid,
        'conflict' => DomainFailureKind::Conflict,
        'not found' => DomainFailureKind::NotFound,
        'unauthenticated' => DomainFailureKind::Unauthenticated,
        'forbidden' => DomainFailureKind::Forbidden,
    ]);

    it('names the code in the developer message it carries', function () {
        expect(UseCaseError::of('owner_already_has_business', DomainFailureKind::Conflict)->toThrowable()->getMessage())
            ->toContain('owner_already_has_business');
    });
});
