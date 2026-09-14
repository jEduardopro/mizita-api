<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

/**
 * The seam that lets everything above the parser be tested without the parsing
 * library, its metadata, or any knowledge of which numbers happen to be real.
 *
 * Hand-rolled rather than a mock because the callers under test - the 422 rule
 * today, Staff and Customers tomorrow - care about two things only: what came
 * back, and whether the parser was consulted at all. Both are questions this
 * answers by hand, and neither reads better as an expectation.
 *
 * It accepts a fixed set of numbers and rejects everything else, which is the
 * real port's whole contract: a PhoneNumber or null, never an exception.
 */
final class FakePhoneNumberParser implements PhoneNumberParser
{
    /** @var array<string, PhoneNumber> */
    private array $accepted = [];

    /** @var list<array{country: CountryCode, nationalNumber: string}> */
    private array $calls = [];

    private function __construct() {}

    /**
     * Every number is unparsable. The state an "invalid phone" test needs.
     */
    public static function acceptingNothing(): self
    {
        return new self;
    }

    public static function accepting(PhoneNumber ...$numbers): self
    {
        $parser = new self;

        foreach ($numbers as $number) {
            $parser->accepted[self::keyFor($number->country(), $number->nationalNumber())] = $number;
        }

        return $parser;
    }

    public function parse(CountryCode $country, string $nationalNumber): ?PhoneNumber
    {
        $this->calls[] = ['country' => $country, 'nationalNumber' => $nationalNumber];

        return $this->accepted[self::keyFor($country, $nationalNumber)] ?? null;
    }

    /**
     * What the parser was asked, in order, exactly as it was asked it - the raw
     * string included, because "the value reached the parser untouched" is part
     * of what a caller has to get right.
     *
     * @return list<array{country: CountryCode, nationalNumber: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function wasConsulted(): bool
    {
        return $this->calls !== [];
    }

    /**
     * The real parser tolerates the separators people type, so the fake matches
     * on digits alone. A caller that passes ' (55) 1234-5678 ' where the fixture
     * says '5512345678' is doing the ordinary thing, not a different thing.
     */
    private static function keyFor(CountryCode $country, string $nationalNumber): string
    {
        return $country->value.':'.preg_replace('/\D/', '', $nationalNumber);
    }
}
