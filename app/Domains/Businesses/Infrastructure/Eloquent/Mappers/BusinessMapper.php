<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Mappers;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\CurrencyCode;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use DateTimeImmutable;

final class BusinessMapper
{
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Business $business, int $industryKey): array
    {
        return [
            'uuid' => $business->id,
            'name' => $business->name(),
            'slug' => $business->slug(),
            'industry_id' => $industryKey,
            'timezone' => $business->timezone(),
            'contact_email' => $business->contactEmail(),
            'about' => $business->about(),
            'currency_code' => $business->currency(),
        ];
    }
}
