<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\GrantSubscriptionInput;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionEndDate;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Shared\Contracts\DomainFailure;

describe('building from a payload', function () {
    it('reads every field of a full payload', function () {
        $input = GrantSubscriptionInput::fromRequest([
            'business' => 'barberia-centro',
            'until' => '2026-06-30',
            'plan' => 'complete',
            'amount' => '15000',
        ]);

        expect($input->businessSlug)->toBe('barberia-centro')
            ->and($input->until)->toBe('2026-06-30')
            ->and($input->plan)->toBe('complete')
            ->and($input->amount)->toBe('15000');
    });

    it('defaults to the complete plan and the list price when neither is given', function () {
        $input = GrantSubscriptionInput::fromRequest(['business' => 'barberia-centro', 'until' => '2026-06-30']);

        expect($input->plan)->toBe(Plan::Complete->value)
            ->and($input->amount)->toBeNull()
            ->and($input->amountInMinorUnits())->toBeNull();
    });

    it('reads a blank or null amount as no amount', function (mixed $amount) {
        expect(GrantSubscriptionInput::fromRequest(['amount' => $amount])->amount)->toBeNull();
    })->with(['empty' => '', 'spaces' => '   ', 'null' => null]);

    it('trims the amount and the plan', function () {
        $input = GrantSubscriptionInput::fromRequest(['plan' => ' complete ', 'amount' => ' 500 ']);

        expect($input->plan)->toBe('complete')
            ->and($input->amount)->toBe('500');
    });

    it('survives an empty payload and refuses it as a domain failure on validation', function () {
        $input = GrantSubscriptionInput::fromRequest([]);

        expect(fn () => $input->validate())->toThrow(InvalidSubscriptionBusinessSlug::class);
    });

    it('defaults the plan to complete when built directly', function () {
        expect((new GrantSubscriptionInput('barberia-centro', '2026-06-30'))->plan())->toBe(Plan::Complete);
    });
});

describe('validating', function () {
    it('accepts a well formed payload', function (array $payload) {
        expect(fn () => GrantSubscriptionInput::fromRequest($payload)->validate())->not->toThrow(Throwable::class);
    })->with([
        'the minimum' => [['business' => 'barberia-centro', 'until' => '2026-06-30']],
        'a custom amount' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '15000']],
        'a complimentary grant' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '0']],
        'fifteen digits' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '999999999999999']],
        'the free plan, whose refusal belongs to the entity' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'plan' => 'free']],
    ]);

    it('rejects a payload the command would have rejected', function (array $payload, string $exception) {
        expect(fn () => GrantSubscriptionInput::fromRequest($payload)->validate())->toThrow($exception);
    })->with([
        'no business' => [['until' => '2026-06-30'], InvalidSubscriptionBusinessSlug::class],
        'a blank business' => [['business' => '   ', 'until' => '2026-06-30'], InvalidSubscriptionBusinessSlug::class],
        'no end date' => [['business' => 'barberia-centro'], InvalidSubscriptionEndDate::class],
        'a malformed end date' => [['business' => 'barberia-centro', 'until' => '30/06/2026'], InvalidSubscriptionEndDate::class],
        'an impossible end date' => [['business' => 'barberia-centro', 'until' => '2026-02-30'], InvalidSubscriptionEndDate::class],
        'an unknown plan' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'plan' => 'premium'], InvalidSubscriptionPlan::class],
        'a capitalised plan' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'plan' => 'Complete'], InvalidSubscriptionPlan::class],
        'a blank plan' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'plan' => '  '], InvalidSubscriptionPlan::class],
        'a negative amount' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '-100'], InvalidSubscriptionPrice::class],
        'a decimal amount' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '199.50'], InvalidSubscriptionPrice::class],
        'a thousands separator' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '20,000'], InvalidSubscriptionPrice::class],
        'letters' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '12abc'], InvalidSubscriptionPrice::class],
        'an exponent' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '1e5'], InvalidSubscriptionPrice::class],
        'sixteen digits' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => '1000000000000000'], InvalidSubscriptionPrice::class],
    ]);

    it('rejects a trailing newline in an amount built directly', function () {
        expect(fn () => (new GrantSubscriptionInput('barberia-centro', '2026-06-30', 'complete', "100\n"))->validate())
            ->toThrow(InvalidSubscriptionPrice::class);
    });

    it('refuses with a domain failure every time', function (array $payload) {
        try {
            GrantSubscriptionInput::fromRequest($payload)->validate();
        } catch (Throwable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class);

            return;
        }

        $this->fail('The payload was accepted.');
    })->with([
        'no business' => [[]],
        'no end date' => [['business' => 'barberia-centro']],
        'an unknown plan' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'plan' => 'premium']],
        'a malformed amount' => [['business' => 'barberia-centro', 'until' => '2026-06-30', 'amount' => 'x']],
    ]);
});

describe('the typed values', function () {
    it('hands over the typed values of a valid payload', function () {
        $input = GrantSubscriptionInput::fromRequest([
            'business' => '  barberia-centro ',
            'until' => '2026-06-30',
            'amount' => '0015000',
        ]);

        expect($input->businessSlug()->value)->toBe('barberia-centro')
            ->and($input->lastIncludedDay()->date)->toBe('2026-06-30')
            ->and($input->plan())->toBe(Plan::Complete)
            ->and($input->amountInMinorUnits())->toBe(15000);
    });

    it('reads a zero amount as zero, never as no amount', function () {
        expect(GrantSubscriptionInput::fromRequest(['amount' => '0'])->amountInMinorUnits())->toBe(0);
    });
});
