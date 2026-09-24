<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;

final readonly class UpdateMyProfileInput
{
    public const MAXIMUM_NAME_LENGTH = 255;

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $accountId,
        public string $name,
        public ?string $jobTitle,
        public ?string $about,
        public ?ProfilePhoneInput $phone,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        return new self(
            accountId: $accountId,
            name: self::textOrEmpty($payload['name'] ?? null),
            jobTitle: self::textOrNull($payload['job_title'] ?? null),
            about: self::textOrNull($payload['about'] ?? null),
            phone: ProfilePhoneInput::fromPayload($payload['phone'] ?? null),
        );
    }

    /**
     * @throws StaffMemberNotFound
     * @throws InvalidProfileName
     * @throws InvalidProfileJobTitle
     * @throws InvalidProfileAbout
     * @throws InvalidProfilePhone
     */
    public function validate(): void
    {
        $this->validateAccountId();
        $this->validateName();
        $this->validateJobTitle();
        $this->validateAbout();
        $this->phone?->validate();
    }

    /**
     * @throws InvalidProfileJobTitle
     */
    public function toJobTitle(): ?JobTitle
    {
        return JobTitle::fromNullable($this->jobTitle);
    }

    /**
     * @throws InvalidProfileAbout
     */
    public function toAbout(): ?About
    {
        return About::fromNullable($this->about);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function validateAccountId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->accountId) !== 1) {
            throw StaffMemberNotFound::forAccount($this->accountId);
        }
    }

    private function validateName(): void
    {
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
        $this->toJobTitle();
    }

    private function validateAbout(): void
    {
        $this->toAbout();
    }
}
