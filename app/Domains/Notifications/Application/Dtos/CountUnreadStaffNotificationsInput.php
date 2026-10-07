<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\Exceptions\InvalidNotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationScope;

final readonly class CountUnreadStaffNotificationsInput
{
    private const DEFAULT_SCOPE = NotificationScope::Mine;

    public function __construct(
        public string $accountId,
        public ?string $scope = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        $scope = $payload['scope'] ?? null;

        return new self(
            accountId: $accountId,
            scope: is_string($scope) ? $scope : null,
        );
    }

    /**
     * @throws InvalidNotificationScope
     */
    public function validate(): void
    {
        $this->validateScope();
    }

    public function scope(): NotificationScope
    {
        return NotificationScope::tryFrom((string) $this->scope) ?? self::DEFAULT_SCOPE;
    }

    private function validateScope(): void
    {
        if ($this->scope !== null && NotificationScope::tryFrom($this->scope) === null) {
            throw InvalidNotificationScope::unknown($this->scope);
        }
    }
}
