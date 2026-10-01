<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\SelectCurrentBusinessInput;
use App\Domains\Businesses\Exceptions\InvalidBusinessSelection;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

const SWITCH_INPUT_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000f00a1';

const SWITCH_INPUT_BUSINESS_UUID = '01930000-0000-7000-8000-0000000f00b1';

describe('building from the request payload', function () {
    it('takes the business the caller named and the account it was handed', function () {
        $input = SelectCurrentBusinessInput::fromRequest(['business_id' => SWITCH_INPUT_BUSINESS_UUID], SWITCH_INPUT_ACCOUNT_UUID);

        expect($input->accountId)->toBe(SWITCH_INPUT_ACCOUNT_UUID)
            ->and($input->businessId)->toBe(SWITCH_INPUT_BUSINESS_UUID);
    });

    it('trims and lowercases the business id', function () {
        $input = SelectCurrentBusinessInput::fromRequest(
            ['business_id' => "  01930000-0000-7000-8000-0000000F00B1 \n"],
            SWITCH_INPUT_ACCOUNT_UUID,
        );

        expect($input->businessId)->toBe(SWITCH_INPUT_BUSINESS_UUID);
    });

    it('never lets the payload choose the account', function () {
        $input = SelectCurrentBusinessInput::fromRequest(
            ['business_id' => SWITCH_INPUT_BUSINESS_UUID, 'account_id' => '01930000-0000-7000-8000-0000000f00a2'],
            SWITCH_INPUT_ACCOUNT_UUID,
        );

        expect($input->accountId)->toBe(SWITCH_INPUT_ACCOUNT_UUID);
    });

    it('reads a business id that is absent or not a string as empty', function (array $payload) {
        expect(SelectCurrentBusinessInput::fromRequest($payload, SWITCH_INPUT_ACCOUNT_UUID)->businessId)->toBe('');
    })->with([
        'no key at all' => [[]],
        'null' => [['business_id' => null]],
        'a number' => [['business_id' => 42]],
        'an array' => [['business_id' => [SWITCH_INPUT_BUSINESS_UUID]]],
        'a boolean' => [['business_id' => true]],
    ]);
});

describe('validating the business it names', function () {
    it('accepts a well formed uuid', function (string $businessId) {
        expect(fn () => (new SelectCurrentBusinessInput(SWITCH_INPUT_ACCOUNT_UUID, $businessId))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'lowercase' => SWITCH_INPUT_BUSINESS_UUID,
        'uppercase, as a direct caller may send it' => strtoupper(SWITCH_INPUT_BUSINESS_UUID),
    ]);

    it('refuses an empty business id as a missing selection', function () {
        expect(fn () => (new SelectCurrentBusinessInput(SWITCH_INPUT_ACCOUNT_UUID, ''))->validate())
            ->toThrow(InvalidBusinessSelection::class, 'No business was named to switch to.');
    });

    it('refuses a business id that is not a uuid', function (string $businessId) {
        expect(fn () => (new SelectCurrentBusinessInput(SWITCH_INPUT_ACCOUNT_UUID, $businessId))->validate())
            ->toThrow(InvalidBusinessSelection::class, "[{$businessId}] is not a business identifier.");
    })->with([
        'a word' => 'barberia-nandu',
        'an int id' => '42',
        'whitespace only' => '   ',
        'one character short' => '01930000-0000-7000-8000-0000000f00b',
        'one character long' => '01930000-0000-7000-8000-0000000f00b11',
        'no dashes' => '0193000000007000800000000000f0b1',
        'braces' => '{01930000-0000-7000-8000-0000000f00b1}',
        'not hexadecimal' => '01930000-0000-7000-8000-0000000g00b1',
        'a trailing newline' => SWITCH_INPUT_BUSINESS_UUID."\n",
        'padded' => ' '.SWITCH_INPUT_BUSINESS_UUID.' ',
        'accented' => '01930000-0000-7000-8000-0000000ñ00b1',
    ]);

    it('refuses a payload with no business at all as a domain failure, not a PHP error', function () {
        $thrown = null;

        try {
            SelectCurrentBusinessInput::fromRequest([], SWITCH_INPUT_ACCOUNT_UUID)->validate();
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(InvalidBusinessSelection::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('invalid_business_selection')
            ->and($thrown->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('classifies a malformed business id the same way as a missing one', function () {
        $thrown = null;

        try {
            (new SelectCurrentBusinessInput(SWITCH_INPUT_ACCOUNT_UUID, 'not-a-uuid'))->validate();
        } catch (InvalidBusinessSelection $failure) {
            $thrown = $failure;
        }

        expect($thrown?->errorCode())->toBe('invalid_business_selection')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('accepts what it built from a padded uppercase payload', function () {
        $input = SelectCurrentBusinessInput::fromRequest(
            ['business_id' => ' '.strtoupper(SWITCH_INPUT_BUSINESS_UUID).' '],
            SWITCH_INPUT_ACCOUNT_UUID,
        );

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });
});
