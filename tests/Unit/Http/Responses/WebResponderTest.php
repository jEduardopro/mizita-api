<?php

declare(strict_types=1);

use App\Http\Responses\WebResponder;
use App\Shared\Application\UseCaseError;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    app()->setLocale('en');

    Route::get('/testing/onboarding', fn () => '')->name('testing.onboarding');
    Route::get('/testing/login', fn () => '')->name('testing.login');
    app('router')->getRoutes()->refreshNameLookups();

    $this->logger = Mockery::spy(LoggerInterface::class);
    $this->responder = new WebResponder($this->logger);
});

function flashedErrors(string $errorKey): string
{
    $errors = session()->get('errors');

    expect($errors)->toBeInstanceOf(ViewErrorBag::class);

    return $errors->first($errorKey);
}

describe('backTo', function () {
    it('redirects to the named route', function () {
        $response = $this->responder->backTo(
            'testing.onboarding',
            'onboarding',
            UseCaseError::of('business_name_taken', DomainFailureKind::Conflict),
        );

        expect($response->getStatusCode())->toBe(302)
            ->and($response->getTargetUrl())->toContain('/testing/onboarding');
    });

    it('flashes the translated failure message under the key it was given', function () {
        $this->responder->backTo(
            'testing.onboarding',
            'onboarding',
            UseCaseError::of('business_name_taken', DomainFailureKind::Conflict),
        );

        expect(flashedErrors('onboarding'))->toBe('There is already a business with that name.');
    });

    it('flashes the same message as the shared flash error', function () {
        $this->responder->backTo(
            'testing.onboarding',
            'onboarding',
            UseCaseError::of('business_name_taken', DomainFailureKind::Conflict),
        );

        expect(session()->get('error'))->toBe('There is already a business with that name.');
    });

    it('speaks the locale the request resolved', function () {
        app()->setLocale('es');

        $this->responder->backTo(
            'testing.onboarding',
            'onboarding',
            UseCaseError::of('business_name_taken', DomainFailureKind::Conflict),
        );

        expect(session()->get('error'))->toBe('Ya existe un negocio con ese nombre.');
    });

    it('logs nothing, because a domain failure is not an incident', function () {
        $this->responder->backTo(
            'testing.onboarding',
            'onboarding',
            UseCaseError::of('business_name_taken', DomainFailureKind::Conflict),
        );

        $this->logger->shouldNotHaveReceived('error');
    });
});

describe('backToWithErrorCode', function () {
    it('redirects to the named route', function () {
        $response = $this->responder->backToWithErrorCode('testing.login', 'google', 'google_sign_in_failed');

        expect($response->getStatusCode())->toBe(302)
            ->and($response->getTargetUrl())->toContain('/testing/login');
    });

    it('flashes the translated message under the key it was given', function () {
        $this->responder->backToWithErrorCode('testing.login', 'google', 'google_sign_in_failed');

        expect(flashedErrors('google'))
            ->toBe('We could not complete the sign in with Google. Please try again.');
    });

    it('puts the message in the default error bag, as a field key and not a named bag', function () {
        $this->responder->backToWithErrorCode('testing.login', 'google', 'google_sign_in_failed');

        $errors = session()->get('errors');

        expect($errors->hasBag('google'))->toBeFalse()
            ->and($errors->getBag('default')->first('google'))
            ->toBe('We could not complete the sign in with Google. Please try again.');
    });

    it('flashes the same message as the shared flash error', function () {
        $this->responder->backToWithErrorCode('testing.login', 'google', 'google_sign_in_failed');

        expect(session()->get('error'))
            ->toBe('We could not complete the sign in with Google. Please try again.');
    });

    it('speaks the locale the request resolved', function () {
        app()->setLocale('es');

        $this->responder->backToWithErrorCode('testing.login', 'google', 'google_sign_in_failed');

        expect(session()->get('error'))
            ->toBe('No hemos podido completar el inicio de sesión con Google. Inténtalo de nuevo.');
    });

    it('logs nothing, because a handshake the caller can retry is not an incident', function () {
        $this->responder->backToWithErrorCode('testing.login', 'google', 'google_sign_in_failed');

        $this->logger->shouldNotHaveReceived('error');
    });
});

describe('unexpected', function () {
    it('redirects back with the opaque server error message', function () {
        $response = $this->responder->unexpected(
            Request::create('/onboarding', 'POST'),
            new RuntimeException('boom'),
            'testing.onboarding',
            'onboarding',
        );

        expect($response->getStatusCode())->toBe(302)
            ->and($response->getTargetUrl())->toContain('/testing/onboarding')
            ->and(session()->get('error'))->toBe('Something went wrong on our side. Please try again.');
    });

    it('never shows the exception message to the visitor', function () {
        $this->responder->unexpected(
            Request::create('/onboarding', 'POST'),
            new RuntimeException('SQLSTATE[42P01]: undefined_table businesses'),
            'testing.onboarding',
            'onboarding',
        );

        expect(flashedErrors('onboarding'))->not->toContain('SQLSTATE');
    });

    it('logs the failure with the exception message and the request context', function () {
        $error = new RuntimeException('boom');

        $this->responder->unexpected(
            Request::create('/onboarding', 'POST'),
            $error,
            'testing.onboarding',
            'onboarding',
        );

        $this->logger->shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'boom'
                && $context['exception'] === $error
                && $context['method'] === 'POST'
                && $context['path'] === 'onboarding');
    });
});
