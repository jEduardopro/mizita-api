<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\Exceptions\InvalidNotificationScope;
use App\Domains\Notifications\Exceptions\InvalidNotificationStatus;
use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Shared\ValueObjects\PageOutOfRange;
use App\Shared\ValueObjects\Pagination;

final readonly class ListStaffNotificationsInput
{
    private const DEFAULT_SCOPE = NotificationScope::Mine;

    private const DEFAULT_STATUS = NotificationStatus::All;

    public function __construct(
        public string $accountId,
        public ?string $scope = null,
        public ?string $status = null,
        public ?int $page = null,
        public ?int $perPage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        return new self(
            accountId: $accountId,
            scope: self::textOrNull($payload['scope'] ?? null),
            status: self::textOrNull($payload['status'] ?? null),
            page: self::countOrNull($payload['page'] ?? null),
            perPage: self::countOrNull($payload['per_page'] ?? null),
        );
    }

    /**
     * @throws InvalidNotificationScope
     * @throws InvalidNotificationStatus
     * @throws PageOutOfRange
     */
    public function validate(): void
    {
        $this->validateScope();
        $this->validateStatus();
        $this->validatePage();
    }

    public function scope(): NotificationScope
    {
        return NotificationScope::tryFrom((string) $this->scope) ?? self::DEFAULT_SCOPE;
    }

    public function status(): NotificationStatus
    {
        return NotificationStatus::tryFrom((string) $this->status) ?? self::DEFAULT_STATUS;
    }

    public function pagination(): Pagination
    {
        return Pagination::of($this->page, $this->perPage);
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function countOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function validateScope(): void
    {
        if ($this->scope !== null && NotificationScope::tryFrom($this->scope) === null) {
            throw InvalidNotificationScope::unknown($this->scope);
        }
    }

    private function validateStatus(): void
    {
        if ($this->status !== null && NotificationStatus::tryFrom($this->status) === null) {
            throw InvalidNotificationStatus::unknown($this->status);
        }
    }

    /**
     * @throws PageOutOfRange
     */
    private function validatePage(): void
    {
        Pagination::ensureValidPage($this->page);
    }
}
