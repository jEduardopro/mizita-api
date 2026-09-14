<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Mappers;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use DateTimeImmutable;

final class BusinessMapper
{
    /**
     * Expects the industry relation to be loaded: the entity carries the
     * industry's uuid and the row carries its int foreign key.
     *
     * Slug and Timezone are restored, not validated - this is the one class
     * their trusting constructors are for, because a read of a row the database
     * already accepted must not fail.
     */
    public function toEntity(BusinessModel $model): Business
    {
        return Business::restore(
            id: $model->uuid,
            name: $model->name,
            slug: Slug::restore($model->slug),
            industryId: $model->industry->uuid,
            timezone: Timezone::restore($model->timezone),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * The industry key is passed in rather than looked up: a mapper that queries
     * is a repository with a different name.
     *
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
        ];
    }
}
