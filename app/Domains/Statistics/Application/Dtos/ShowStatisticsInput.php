<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Exceptions\StatisticsPeriodTooWide;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Domains\Statistics\ValueObjects\StatisticsPeriod;

final readonly class ShowStatisticsInput
{
    public function __construct(
        public ?string $from,
        public ?string $to,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            from: self::dateOrNull($payload['from'] ?? null),
            to: self::dateOrNull($payload['to'] ?? null),
        );
    }

    /**
     * @throws InvalidStatisticsPeriod
     * @throws StatisticsPeriodTooWide
     */
    public function validate(): void
    {
        $this->toPeriod();
    }

    /**
     * @throws InvalidStatisticsPeriod
     * @throws StatisticsPeriodTooWide
     */
    public function toPeriod(): ?StatisticsPeriod
    {
        if ($this->from === null && $this->to === null) {
            return null;
        }

        if ($this->from === null || $this->to === null) {
            throw InvalidStatisticsPeriod::incomplete();
        }

        return StatisticsPeriod::between(LocalDate::fromString($this->from), LocalDate::fromString($this->to));
    }

    private static function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
