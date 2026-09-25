<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\CompleteCalendarAuthorizationInput;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationDenied;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Shared\Contracts\DomainFailure;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;

describe('building from the callback query', function () {
    it('reads the state, the code and the error, and takes the account from the caller', function () {
        $input = CompleteCalendarAuthorizationInput::fromRequest([
            'state' => IntegrationsFixtures::STATE,
            'code' => IntegrationsFixtures::CODE,
            'error' => 'access_denied',
        ], IntegrationsFixtures::ACCOUNT_ID);

        expect($input->state)->toBe(IntegrationsFixtures::STATE)
            ->and($input->code)->toBe(IntegrationsFixtures::CODE)
            ->and($input->error)->toBe('access_denied')
            ->and($input->accountId)->toBe(IntegrationsFixtures::ACCOUNT_ID);
    });

    it('ignores an account the query tries to smuggle in', function () {
        $input = CompleteCalendarAuthorizationInput::fromRequest([
            'state' => IntegrationsFixtures::STATE,
            'code' => IntegrationsFixtures::CODE,
            'accountId' => IntegrationsFixtures::OTHER_ACCOUNT_ID,
            'account_id' => IntegrationsFixtures::OTHER_ACCOUNT_ID,
        ], IntegrationsFixtures::ACCOUNT_ID);

        expect($input->accountId)->toBe(IntegrationsFixtures::ACCOUNT_ID);
    });

    it('turns missing keys into empty text rather than a php error', function () {
        $input = CompleteCalendarAuthorizationInput::fromRequest([], IntegrationsFixtures::ACCOUNT_ID);

        expect($input->state)->toBe('')
            ->and($input->code)->toBe('')
            ->and($input->error)->toBe('');
    });

    it('turns a payload with every key missing into a domain failure', function () {
        expect(fn () => CompleteCalendarAuthorizationInput::fromRequest([], IntegrationsFixtures::ACCOUNT_ID)->validate())
            ->toThrow(CalendarAuthorizationStateInvalid::class);
    });

    it('turns values that are not text into empty text', function (mixed $value) {
        $input = CompleteCalendarAuthorizationInput::fromRequest(
            ['state' => $value, 'code' => $value, 'error' => $value],
            IntegrationsFixtures::ACCOUNT_ID,
        );

        expect($input->state)->toBe('')
            ->and($input->code)->toBe('')
            ->and($input->error)->toBe('');
    })->with([
        'null' => [null],
        'array' => [[IntegrationsFixtures::STATE]],
        'integer' => [42],
        'boolean' => [true],
    ]);
});

describe('validating the callback', function () {
    it('accepts a granted callback', function () {
        expect(fn () => IntegrationsFixtures::completeInput()->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a state of exactly the maximum length', function () {
        expect(fn () => IntegrationsFixtures::completeInput(state: str_repeat('s', 128))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('measures the state in characters, not bytes', function () {
        expect(fn () => IntegrationsFixtures::completeInput(state: str_repeat('ñ', 128))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('rejects a callback the provider or the caller got wrong', function (CompleteCalendarAuthorizationInput $input, string $exception, string $code) {
        try {
            $input->validate();
            $this->fail('validate() accepted a callback it should have refused.');
        } catch (DomainFailure $failure) {
            expect($failure)->toBeInstanceOf($exception)
                ->and($failure->errorCode())->toBe($code);
        }
    })->with([
        'empty state' => [
            fn () => IntegrationsFixtures::completeInput(state: ''),
            CalendarAuthorizationStateInvalid::class,
            'calendar_authorization_state_invalid',
        ],
        'whitespace state' => [
            fn () => IntegrationsFixtures::completeInput(state: " \t\n"),
            CalendarAuthorizationStateInvalid::class,
            'calendar_authorization_state_invalid',
        ],
        'state one character too long' => [
            fn () => IntegrationsFixtures::completeInput(state: str_repeat('s', 129)),
            CalendarAuthorizationStateInvalid::class,
            'calendar_authorization_state_invalid',
        ],
        'user denied the consent' => [
            fn () => IntegrationsFixtures::completeInput(code: '', error: 'access_denied'),
            CalendarAuthorizationDenied::class,
            'calendar_authorization_denied',
        ],
        'provider error' => [
            fn () => IntegrationsFixtures::completeInput(code: '', error: 'invalid_scope'),
            CalendarAuthorizationFailed::class,
            'calendar_authorization_failed',
        ],
        'error alongside a code' => [
            fn () => IntegrationsFixtures::completeInput(error: 'server_error'),
            CalendarAuthorizationFailed::class,
            'calendar_authorization_failed',
        ],
        'no code' => [
            fn () => IntegrationsFixtures::completeInput(code: ''),
            CalendarAuthorizationFailed::class,
            'calendar_authorization_failed',
        ],
        'whitespace code' => [
            fn () => IntegrationsFixtures::completeInput(code: '   '),
            CalendarAuthorizationFailed::class,
            'calendar_authorization_failed',
        ],
    ]);

    it('checks the state before it reports a denial', function () {
        expect(fn () => IntegrationsFixtures::completeInput(state: '', code: '', error: 'access_denied')->validate())
            ->toThrow(CalendarAuthorizationStateInvalid::class);
    });

    it('reports a denial rather than a missing code', function () {
        expect(fn () => IntegrationsFixtures::completeInput(code: '', error: 'access_denied')->validate())
            ->toThrow(CalendarAuthorizationDenied::class);
    });

    it('treats a denial spelled differently as a provider error', function () {
        expect(fn () => IntegrationsFixtures::completeInput(code: '', error: 'ACCESS_DENIED')->validate())
            ->toThrow(CalendarAuthorizationFailed::class);
    });
});
