<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Mappers;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class BusinessMapper
{
    private const STORAGE_TIMEZONE = 'UTC';

    public function toEntity(BusinessModel $model): Business
    {
        return Business::restore(
            id: $model->uuid,
            name: $model->name,
            slug: Slug::restore($model->slug),
            industryId: $model->industry->uuid,
            timezone: Timezone::restore($model->timezone),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            contactEmail: $model->contact_email === null ? null : ContactEmail::restore($model->contact_email),
            about: $model->about === null ? null : About::restore($model->about),
            currency: CurrencyCode::restore($model->currency_code),
            closedAt: self::instantOrNull($model->closed_at),
            closedByAccountId: $model->closedBy?->uuid,
            purgedAt: self::instantOrNull($model->purged_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Business $business, int $industryKey, ?int $closedByAccountKey): array
    {
        $closedAt = self::storedInstant($business->closedAt());

        return [
            'uuid' => $business->id,
            'name' => $business->name(),
            'slug' => $business->slug(),
            'industry_id' => $industryKey,
            'timezone' => $business->timezone(),
            'contact_email' => $business->contactEmail(),
            'about' => $business->about(),
            'currency_code' => $business->currency(),
            'closed_at' => $closedAt,
            'closed_by_account_id' => $closedByAccountKey,
            'purged_at' => self::storedInstant($business->purgedAt()),
            'deleted_at' => $closedAt,
        ];
    }

    private static function instantOrNull(?DateTimeInterface $instant): ?DateTimeImmutable
    {
        return $instant === null ? null : DateTimeImmutable::createFromInterface($instant);
    }

    private static function storedInstant(?DateTimeImmutable $instant): ?DateTimeImmutable
    {
        return $instant?->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
