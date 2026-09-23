<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ListPublicStatesInput;
use App\Domains\PublicCatalog\Exceptions\UnsupportedPublicCountry;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;

describe('reading the country out of the query string', function () {
    it('keeps a supported code the visitor sent', function (string $sent, string $expected) {
        expect(ListPublicStatesInput::fromRequest(['country' => $sent])->countryCode)->toBe($expected);
    })->with([
        'mexico' => ['MX', 'MX'],
        'united states' => ['US', 'US'],
    ]);

    it('uppercases a code sent in any case', function (string $sent, string $expected) {
        expect(ListPublicStatesInput::fromRequest(['country' => $sent])->countryCode)->toBe($expected);
    })->with([
        'lowercase' => ['us', 'US'],
        'mixed case' => ['Mx', 'MX'],
        'accented lowercase' => ['ñx', 'ÑX'],
    ]);

    it('trims the whitespace around the code', function (string $sent) {
        expect(ListPublicStatesInput::fromRequest(['country' => $sent])->countryCode)->toBe('US');
    })->with([
        'leading spaces' => ['  US'],
        'trailing spaces' => ['US  '],
        'tabs and newlines' => ["\tus\n"],
    ]);

    it('falls back to mexico when the visitor names no country', function (array $payload) {
        expect(ListPublicStatesInput::fromRequest($payload)->countryCode)->toBe('MX');
    })->with([
        'no country key' => [[]],
        'an empty string' => [['country' => '']],
        'whitespace only' => [['country' => "  \t "]],
        'null' => [['country' => null]],
        'an integer' => [['country' => 52]],
        'a boolean' => [['country' => true]],
        'an array' => [['country' => ['US']]],
    ]);

    it('ignores every key it does not read', function () {
        expect(ListPublicStatesInput::fromRequest(['country' => 'US', 'business_id' => 7])->countryCode)->toBe('US');
    });
});

describe('validating the country', function () {
    it('accepts every country the platform operates in', function (string $code, CountryCode $country) {
        $input = ListPublicStatesInput::fromRequest(['country' => $code]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class)
            ->and($input->country())->toBe($country);
    })->with([
        'mexico' => ['mx', CountryCode::Mx],
        'united states' => ['us', CountryCode::Us],
    ]);

    it('accepts the default a visitor gets when naming no country', function () {
        $input = ListPublicStatesInput::fromRequest([]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class)
            ->and($input->country())->toBe(CountryCode::Mx);
    });

    it('rejects a code the platform does not operate in', function (string $code) {
        expect(fn () => ListPublicStatesInput::fromRequest(['country' => $code])->validate())
            ->toThrow(UnsupportedPublicCountry::class);
    })->with([
        'a real but unsupported country' => ['BR'],
        'a made up code' => ['XX'],
        'a three letter code' => ['MEX'],
        'a single letter' => ['M'],
        'an accented code' => ['ñx'],
    ]);

    it('rejects a lowercase code handed straight to the constructor, because only fromRequest normalises', function () {
        expect(fn () => (new ListPublicStatesInput('mx'))->validate())->toThrow(UnsupportedPublicCountry::class);
    });

    it('rejects a blank code handed straight to the constructor, because only fromRequest defaults', function () {
        expect(fn () => (new ListPublicStatesInput(''))->validate())->toThrow(UnsupportedPublicCountry::class);
    });

    it('refuses with an invalid payload failure coded unsupported_country', function () {
        try {
            ListPublicStatesInput::fromRequest(['country' => 'BR'])->validate();
        } catch (UnsupportedPublicCountry $refusal) {
            expect($refusal)->toBeInstanceOf(DomainFailure::class)
                ->and($refusal->errorCode())->toBe('unsupported_country')
                ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail('An unsupported country was accepted.');
    });

    it('has a sentence to show the visitor in every locale', function (string $locale) {
        $messages = require dirname(__DIR__, 6)."/lang/{$locale}/messages.php";

        expect($messages['errors']['unsupported_country'] ?? '')->toBeString()->not->toBe('');
    })->with(['en', 'es']);
});
