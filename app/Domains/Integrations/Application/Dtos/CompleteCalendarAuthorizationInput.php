<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

use App\Domains\Integrations\Exceptions\CalendarAuthorizationDenied;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;

final readonly class CompleteCalendarAuthorizationInput
{
    private const ACCESS_DENIED = 'access_denied';

    private const MAXIMUM_STATE_LENGTH = 128;

    public function __construct(
        public string $state,
        public string $code,
        public string $error,
        public string $accountId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        return new self(
            state: self::textFrom($payload, 'state'),
            code: self::textFrom($payload, 'code'),
            error: self::textFrom($payload, 'error'),
            accountId: $accountId,
        );
    }

    /**
     * @throws CalendarAuthorizationStateInvalid
     * @throws CalendarAuthorizationDenied
     * @throws CalendarAuthorizationFailed
     */
    public function validate(): void
    {
        $this->validateState();
        $this->validateGranted();
        $this->validateCode();
    }

    private function validateState(): void
    {
        $state = trim($this->state);

        if ($state === '' || mb_strlen($state) > self::MAXIMUM_STATE_LENGTH) {
            throw CalendarAuthorizationStateInvalid::missing();
        }
    }

    private function validateGranted(): void
    {
        if ($this->error === self::ACCESS_DENIED) {
            throw CalendarAuthorizationDenied::byUser();
        }

        if ($this->error !== '') {
            throw CalendarAuthorizationFailed::providerError($this->error);
        }
    }

    private function validateCode(): void
    {
        if (trim($this->code) === '') {
            throw CalendarAuthorizationFailed::missingCode();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function textFrom(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
