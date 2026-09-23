<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Services;

use App\Domains\Addresses\Entities\State;
use App\Shared\ValueObjects\CountryCode;

final class StateMatcher
{
    private const WHITESPACE_RUN = '/\s+/u';

    private const SINGLE_SPACE = ' ';

    private const TO_ASCII = 'Any-Latin; Latin-ASCII';

    public function canMatch(string $nameOrCode): bool
    {
        return self::comparable($nameOrCode) !== '';
    }

    /**
     * @param  list<State>  $states
     */
    public function bestMatch(array $states, CountryCode $preferredCountry, string $nameOrCode): ?State
    {
        if (! $this->canMatch($nameOrCode)) {
            return null;
        }

        $wanted = self::comparable($nameOrCode);

        $matches = array_values(array_filter(
            $states,
            fn (State $state): bool => self::isNamedOrCoded($state, $wanted),
        ));

        $rankByCountry = self::rankByCountryPreferring($preferredCountry);

        usort(
            $matches,
            fn (State $first, State $second): int => $rankByCountry[$first->country()->value] <=> $rankByCountry[$second->country()->value],
        );

        return $matches[0] ?? null;
    }

    private static function isNamedOrCoded(State $state, string $wanted): bool
    {
        return self::comparable($state->name()) === $wanted || self::comparable($state->code()) === $wanted;
    }

    /**
     * @return array<string, int>
     */
    private static function rankByCountryPreferring(CountryCode $preferredCountry): array
    {
        $otherCountries = array_filter(
            CountryCode::cases(),
            fn (CountryCode $country): bool => $country !== $preferredCountry,
        );

        return array_flip(array_column([$preferredCountry, ...$otherCountries], 'value'));
    }

    private static function comparable(string $value): string
    {
        $collapsed = trim((string) preg_replace(self::WHITESPACE_RUN, self::SINGLE_SPACE, $value));

        return (string) transliterator_transliterate(self::TO_ASCII, mb_strtolower($collapsed));
    }
}
