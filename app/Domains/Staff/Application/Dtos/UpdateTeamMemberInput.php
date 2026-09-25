<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use App\Domains\Staff\ValueObjects\StaffMemberId;
use App\Domains\Staff\ValueObjects\StaffRole;

final readonly class UpdateTeamMemberInput
{
    public const MAXIMUM_NAME_LENGTH = 255;

    public function __construct(
        public string $staffMemberId,
        public ?string $name,
        public ?TextChange $jobTitle,
        public ?TextChange $about,
        public ?PhoneChange $phone,
        public ?string $level,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $staffMemberId): self
    {
        return new self(
            staffMemberId: $staffMemberId,
            name: self::textOrNull($payload['name'] ?? null),
            jobTitle: TextChange::fromPayload($payload, 'job_title'),
            about: TextChange::fromPayload($payload, 'about'),
            phone: PhoneChange::fromPayload($payload),
            level: self::textOrNull($payload['level'] ?? null),
        );
    }

    /**
     * @throws StaffMemberNotFound
     * @throws InvalidProfileName
     * @throws InvalidProfileJobTitle
     * @throws InvalidProfileAbout
     * @throws InvalidProfilePhone
     * @throws InvalidTeamLevel
     */
    public function validate(): void
    {
        $this->validateStaffMemberId();
        $this->validateName();
        $this->validateJobTitle();
        $this->validateAbout();
        $this->phone?->validate();
        $this->validateLevel();
    }

    /**
     * @throws InvalidProfileJobTitle
     */
    public function toJobTitle(TextChange $change): ?JobTitle
    {
        return JobTitle::fromNullable($change->value);
    }

    /**
     * @throws InvalidProfileAbout
     */
    public function toAbout(TextChange $change): ?About
    {
        return About::fromNullable($change->value);
    }

    /**
     * @throws InvalidTeamLevel
     */
    public function toLevel(string $level): StaffRole
    {
        return StaffRole::assignableFrom($level);
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function validateStaffMemberId(): void
    {
        StaffMemberId::fromString($this->staffMemberId);
    }

    private function validateName(): void
    {
        if ($this->name === null) {
            return;
        }

        $name = trim($this->name);

        if ($name === '') {
            throw InvalidProfileName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidProfileName::tooLong(self::MAXIMUM_NAME_LENGTH);
        }
    }

    private function validateJobTitle(): void
    {
        if ($this->jobTitle !== null) {
            $this->toJobTitle($this->jobTitle);
        }
    }

    private function validateAbout(): void
    {
        if ($this->about !== null) {
            $this->toAbout($this->about);
        }
    }

    private function validateLevel(): void
    {
        if ($this->level !== null) {
            $this->toLevel($this->level);
        }
    }
}
