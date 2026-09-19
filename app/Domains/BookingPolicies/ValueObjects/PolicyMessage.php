<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

use App\Domains\BookingPolicies\Exceptions\InvalidPolicyMessage;

final readonly class PolicyMessage
{
    public const MAXIMUM_LENGTH = 2000;

    private function __construct(
        private ?string $text,
    ) {}

    /**
     * @throws InvalidPolicyMessage
     */
    public static function fromString(?string $text): self
    {
        if ($text === null) {
            return self::none();
        }

        $message = trim($text);

        if ($message === '') {
            return self::none();
        }

        if (mb_strlen($message) > self::MAXIMUM_LENGTH) {
            throw InvalidPolicyMessage::tooLong(self::MAXIMUM_LENGTH);
        }

        return new self($message);
    }

    public static function none(): self
    {
        return new self(null);
    }

    public static function restore(?string $text): self
    {
        return new self($text);
    }

    public function toString(): ?string
    {
        return $this->text;
    }

    public function equals(self $other): bool
    {
        return $this->text === $other->text;
    }
}
