<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApiFailure;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;

it('recognises a refusal about a resource that does not exist', function () {
    $failure = StripeApiFailure::requestFailed(
        'GET /v1/checkout/sessions/{id}',
        InvalidRequestException::factory('No such checkout.session', 404),
    );

    expect($failure->concernsMissingResource())->toBeTrue();
});

it('does not read any other refusal as a missing resource', function (ApiErrorException $cause) {
    expect(StripeApiFailure::requestFailed('GET /v1/subscriptions/{id}', $cause)->concernsMissingResource())->toBeFalse();
})->with([
    'a malformed request' => fn () => InvalidRequestException::factory('Invalid request', 400),
    'a rejected key' => fn () => AuthenticationException::factory('Invalid API key', 401),
    'no answer at all' => fn () => ApiConnectionException::factory('Could not connect'),
]);

it('names the operation and keeps the original refusal as its cause', function () {
    $cause = InvalidRequestException::factory('No such subscription', 404);

    $failure = StripeApiFailure::requestFailed('GET /v1/subscriptions/{id}', $cause);

    expect($failure->getMessage())->toContain('GET /v1/subscriptions/{id}')
        ->and($failure->getPrevious())->toBe($cause);
});
