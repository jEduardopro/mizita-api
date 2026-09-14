<?php

declare(strict_types=1);

use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Responses\FailurePayload;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

const FOREIGN_BUSINESS_UUID = '01930000-0000-7000-8000-00000000beef';

beforeEach(function () {
    app()->setLocale('en');
});

function deniedOnTheWire(BusinessAccessDenied $failure): array
{
    $response = (new RenderDomainFailure)($failure, Request::create('/api/businesses', 'GET'));

    return json_decode($response->getContent(), associative: true);
}

describe('accountHasNoBusiness', function () {
    it('names itself no_business', function () {
        expect(BusinessAccessDenied::accountHasNoBusiness()->errorCode())->toBe('no_business');
    });

    it('is forbidden, so the api answers 403', function () {
        $failure = BusinessAccessDenied::accountHasNoBusiness();

        expect($failure->kind())->toBe(DomainFailureKind::Forbidden)
            ->and(FailurePayload::for($failure->errorCode(), $failure->kind())->status)->toBe(403);
    });

    it('renders the flat message and code body the client expects', function () {
        expect(deniedOnTheWire(BusinessAccessDenied::accountHasNoBusiness()))->toBe([
            'message' => 'This user does not belong to a business.',
            'code' => 'no_business',
        ]);
    });
});

describe('businessNotAccessible', function () {
    it('names itself business_not_accessible', function () {
        expect(BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID)->errorCode())
            ->toBe('business_not_accessible');
    });

    it('is forbidden, so the api answers 403', function () {
        $failure = BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID);

        expect($failure->kind())->toBe(DomainFailureKind::Forbidden)
            ->and(FailurePayload::for($failure->errorCode(), $failure->kind())->status)->toBe(403);
    });

    it('renders the flat message and code body the client expects', function () {
        expect(deniedOnTheWire(BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID)))->toBe([
            'message' => 'You do not have access to that business.',
            'code' => 'business_not_accessible',
        ]);
    });

    it('keeps the requested business in the developer message, for the log and nowhere else', function () {
        expect(BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID)->getMessage())
            ->toContain(FOREIGN_BUSINESS_UUID);
    });
});

describe('the identifier it carries never reaches the client', function () {
    it('keeps the requested business uuid out of the rendered body', function () {
        $body = deniedOnTheWire(BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID));

        expect($body['message'])->not->toContain(FOREIGN_BUSINESS_UUID)
            ->and($body['code'])->not->toContain(FOREIGN_BUSINESS_UUID)
            ->and(json_encode($body))->not->toContain(FOREIGN_BUSINESS_UUID);
    });

    it('keeps it out of the body in every locale we ship', function (string $locale) {
        app()->setLocale($locale);

        expect(json_encode(deniedOnTheWire(BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID))))
            ->not->toContain(FOREIGN_BUSINESS_UUID);
    })->with(['en', 'es']);

    it('sends the translation keyed by the error code, not the developer message', function () {
        $failure = BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID);

        expect(deniedOnTheWire($failure)['message'])
            ->toBe(FailurePayload::messageFor('business_not_accessible'))
            ->not->toBe($failure->getMessage());
    });

    it('answers a foreign business and an absent one with the very same body, so neither confirms the other', function () {
        $absent = deniedOnTheWire(BusinessAccessDenied::businessNotAccessible('01930000-0000-7000-8000-0000000000ff'));
        $foreign = deniedOnTheWire(BusinessAccessDenied::businessNotAccessible(FOREIGN_BUSINESS_UUID));

        expect($absent)->toBe($foreign);
    });
});

describe('its translations', function () {
    it('has a message in both locales for every code it can report', function (string $locale, string $code, string $message) {
        app()->setLocale($locale);

        expect(FailurePayload::messageFor($code))->toBe($message);
    })->with([
        ['en', 'no_business', 'This user does not belong to a business.'],
        ['en', 'business_not_accessible', 'You do not have access to that business.'],
        ['es', 'no_business', 'Este usuario no pertenece a ningún negocio.'],
        ['es', 'business_not_accessible', 'No tienes acceso a ese negocio.'],
    ]);

    it('never falls back to the raw translation key', function (string $locale, string $code) {
        app()->setLocale($locale);

        expect(FailurePayload::messageFor($code))->not->toStartWith('messages.errors.');
    })->with(['en', 'es'])->with(['no_business', 'business_not_accessible']);
});

describe('its shape', function () {
    it('is a throwable domain failure, so the handler can both catch and classify it', function () {
        $failure = BusinessAccessDenied::accountHasNoBusiness();

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure)->toBeInstanceOf(Throwable::class)
            ->and($failure)->toBeInstanceOf(RuntimeException::class);
    });

    it('can only be built through its named constructors', function () {
        expect((new ReflectionClass(BusinessAccessDenied::class))->getConstructor()->isPrivate())->toBeTrue();
    });

    it('lives in the http kernel and not in a domain, because the middleware is not a domain', function () {
        $file = (new ReflectionClass(BusinessAccessDenied::class))->getFileName();

        expect(BusinessAccessDenied::class)->toStartWith('App\Http\Exceptions\\')
            ->and($file)->toContain('/app/Http/Exceptions/')
            ->and($file)->not->toContain('/app/Domains/');
    });
});
