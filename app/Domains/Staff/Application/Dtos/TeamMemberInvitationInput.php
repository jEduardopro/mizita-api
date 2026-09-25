<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\ValueObjects\InviteeEmail;
use App\Domains\Staff\ValueObjects\StaffRole;

final readonly class TeamMemberInvitationInput
{
    public const MAXIMUM_NAME_LENGTH = 255;

    public function __construct(
        public string $name,
        public string $email,
        public string $level,
    ) {}

    public static function fromPayload(mixed $payload): self
    {
        $row = is_array($payload) ? $payload : [];

        return new self(
            name: self::textOrEmpty($row['name'] ?? null),
            email: self::textOrEmpty($row['email'] ?? null),
            level: self::textOrEmpty($row['level'] ?? null),
        );
    }

    /**
     * @throws InvalidProfileName
     * @throws InvalidTeamMemberEmail
     * @throws InvalidTeamLevel
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateEmail();
        $this->validateLevel();
    }

    public function trimmedName(): string
    {
        return trim($this->name);
    }

    /**
     * @throws InvalidTeamMemberEmail
     */
    public function toEmail(): InviteeEmail
    {
        return InviteeEmail::fromString($this->email);
    }

    /**
     * @throws InvalidTeamLevel
     */
    public function toLevel(): StaffRole
    {
        return StaffRole::assignableFrom($this->level);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateName(): void
    {
        $name = $this->trimmedName();

        if ($name === '') {
            throw InvalidProfileName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidProfileName::tooLong(self::MAXIMUM_NAME_LENGTH);
        }
    }

    private function validateEmail(): void
    {
        $this->toEmail();
    }

    private function validateLevel(): void
    {
        $this->toLevel();
    }
}
