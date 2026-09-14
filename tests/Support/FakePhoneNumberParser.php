<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

final class FakePhoneNumberParser implements PhoneNumberParser
{
    /** @var array<string, PhoneNumber> */
    private array $accepted = [];

    /** @var list<array{country: CountryCode, nationalNumber: string}> */
    private array $calls = [];

    private function __construct() {}

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

    private static function keyFor(CountryCode $country, string $nationalNumber): string
    {
        return $country->value.':'.preg_replace('/\D/', '', $nationalNumber);
    }
}
