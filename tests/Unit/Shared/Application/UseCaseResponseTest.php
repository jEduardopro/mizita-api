<?php

declare(strict_types=1);

use App\Shared\Application\UseCaseResponse;
use App\Shared\Application\Warning;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Unit\Shared\Application\FailingDomainRule;

describe('a response a use case succeeded with', function () {
    it('hands back the very value the use case produced', function () {
        $data = new stdClass;

        $response = UseCaseResponse::success($data);

        expect($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->value())->toBe($data)
            ->and($response->warnings())->toBe([]);
    });

    it('lets a use case with nothing to return succeed on null', function () {
        $response = UseCaseResponse::success();

        expect($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->value())->toBeNull();
    });

    it('keeps a falsy value distinguishable from a failure', function (mixed $data) {
        $response = UseCaseResponse::success($data);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe($data);
    })->with([
        'empty list' => [[]],
        'empty string' => [''],
        'zero' => [0],
        'false' => [false],
    ]);

    it('refuses to describe an error it does not have', function () {
        expect(fn () => UseCaseResponse::success('anything')->error())
            ->toThrow(LogicException::class);
    });

    it('carries the warnings it was handed at construction', function () {
        $response = UseCaseResponse::success('anything', [new Warning('phone_not_attached')]);

        expect($response->warnings())->toHaveCount(1)
            ->and($response->warnings()[0]->code)->toBe('phone_not_attached');
    });
});

describe('a response a domain failure ended', function () {
    it('takes the code and the kind from the exception that classified itself', function () {
        $failure = new FailingDomainRule('business_name_already_taken', DomainFailureKind::Conflict);

        $response = UseCaseResponse::failure($failure);

        expect($response->failed())->toBeTrue()
            ->and($response->succeeded())->toBeFalse()
            ->and($response->error()->code)->toBe('business_name_already_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('rethrows the very exception instance the domain threw', function () {
        $failure = new FailingDomainRule;
        $response = UseCaseResponse::failure($failure);

        $thrown = null;

        try {
            $response->value();
        } catch (Throwable $caught) {
            $thrown = $caught;
        }

        expect($thrown)->toBe($failure);
    });

    it('lets a caller catch the one exception class it knows how to translate', function () {
        $response = UseCaseResponse::failure(new FailingDomainRule);

        expect(fn () => $response->value())->toThrow(FailingDomainRule::class);
    });

    it('keeps the original exception reachable as the cause', function () {
        $failure = new FailingDomainRule;

        expect(UseCaseResponse::failure($failure)->error()->cause())->toBe($failure)
            ->and(UseCaseResponse::failure($failure)->error()->toThrowable())->toBe($failure);
    });

    it('starts with no warnings', function () {
        expect(UseCaseResponse::failure(new FailingDomainRule)->warnings())->toBe([]);
    });
});

describe('a response a use case refused without an exception', function () {
    it('records the kind its named constructor stands for', function (string $constructor, DomainFailureKind $kind) {
        $response = UseCaseResponse::{$constructor}('business_name_not_sluggable');

        expect($response->failed())->toBeTrue()
            ->and($response->succeeded())->toBeFalse()
            ->and($response->error()->code)->toBe('business_name_not_sluggable')
            ->and($response->error()->kind)->toBe($kind);
    })->with([
        'invalid' => ['invalid', DomainFailureKind::Invalid],
        'conflict' => ['conflict', DomainFailureKind::Conflict],
        'notFound' => ['notFound', DomainFailureKind::NotFound],
        'unauthenticated' => ['unauthenticated', DomainFailureKind::Unauthenticated],
        'forbidden' => ['forbidden', DomainFailureKind::Forbidden],
    ]);

    it('has no exception to blame', function (string $constructor) {
        expect(UseCaseResponse::{$constructor}('unknown_industry')->error()->cause())->toBeNull();
    })->with(['invalid', 'conflict', 'notFound', 'unauthenticated', 'forbidden']);

    it('falls back to a UseCaseFailed carrying the same code and kind', function (string $constructor, DomainFailureKind $kind) {
        $thrown = null;

        try {
            UseCaseResponse::{$constructor}('unknown_industry')->value();
        } catch (Throwable $caught) {
            $thrown = $caught;
        }

        expect($thrown)->toBeInstanceOf(UseCaseFailed::class)
            ->and($thrown->errorCode())->toBe('unknown_industry')
            ->and($thrown->kind())->toBe($kind);
    })->with([
        'invalid' => ['invalid', DomainFailureKind::Invalid],
        'conflict' => ['conflict', DomainFailureKind::Conflict],
        'notFound' => ['notFound', DomainFailureKind::NotFound],
        'unauthenticated' => ['unauthenticated', DomainFailureKind::Unauthenticated],
        'forbidden' => ['forbidden', DomainFailureKind::Forbidden],
    ]);
});

describe('warnings added after the fact', function () {
    it('leaves the response it was added to untouched', function () {
        $original = UseCaseResponse::success('business');

        $warned = $original->addWarning('phone_not_attached');

        expect($warned)->not->toBe($original)
            ->and($original->warnings())->toBe([])
            ->and($warned->warnings())->toHaveCount(1);
    });

    it('keeps the data the response was already carrying', function () {
        $data = new stdClass;

        $warned = UseCaseResponse::success($data)->addWarning('phone_not_attached');

        expect($warned->succeeded())->toBeTrue()
            ->and($warned->value())->toBe($data);
    });

    it('accumulates in the order they were raised', function () {
        $warned = UseCaseResponse::success('business')
            ->addWarning('phone_not_attached')
            ->addWarning('welcome_email_not_sent');

        expect(array_map(
            static fn (Warning $warning): string => $warning->code,
            $warned->warnings(),
        ))->toBe(['phone_not_attached', 'welcome_email_not_sent']);
    });

    it('survives on a failed response without turning it into a success', function () {
        $failure = new FailingDomainRule;

        $warned = UseCaseResponse::failure($failure)->addWarning('phone_not_attached');

        expect($warned->failed())->toBeTrue()
            ->and($warned->error()->cause())->toBe($failure)
            ->and($warned->warnings()[0]->code)->toBe('phone_not_attached');
    });

    it('survives on a refusal that never carried an exception', function () {
        $warned = UseCaseResponse::conflict('business_slug_already_taken')->addWarning('phone_not_attached');

        expect($warned->failed())->toBeTrue()
            ->and($warned->error()->code)->toBe('business_slug_already_taken')
            ->and($warned->warnings()[0]->code)->toBe('phone_not_attached');
    });
});
