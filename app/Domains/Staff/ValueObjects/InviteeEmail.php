<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;

final readonly class InviteeEmail
{
    public const MAXIMUM_LENGTH = 254;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidTeamMemberEmail
     */
    public static function fromString(string $value): self
    {
        $email = mb_strtolower(trim($value));

        if ($email === '') {
            throw InvalidTeamMemberEmail::empty();
        }

        if (mb_strlen($email) > self::MAXIMUM_LENGTH) {
            throw InvalidTeamMemberEmail::tooLong(self::MAXIMUM_LENGTH);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidTeamMemberEmail::malformed($email);
        }

        return new self($email);
    }
}
