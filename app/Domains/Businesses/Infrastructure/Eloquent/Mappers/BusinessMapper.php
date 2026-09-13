<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Mappers;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use DateTimeImmutable;

/**
 * Translates between the persistence model and the domain entity. Only the
 * repository adapter uses it.
 */
final class BusinessMapper
{
    /**
     * Expects the industry relation to be loaded: the entity carries the
     * industry's uuid, the row carries its int foreign key, and this is where
     * the two meet.
     *
     * Slug and Timezone are restored, not validated. This is the one class
     * their trusting constructors are for: a read of a row the database already
     * accepted must not fail, so creation-time rules are skipped here exactly
     * as Business::restore skips its own.
     */
    public function toEntity(BusinessModel $model): Business
    {
        return Business::restore(
            // The uuid is the domain identity; the int primary key stays here.
            id: $model->uuid,
            name: $model->name,
            slug: Slug::restore($model->slug),
            industryId: $model->industry->uuid,
            timezone: Timezone::restore($model->timezone),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * The industry key is passed in rather than looked up here: resolving a
     * uuid to a foreign key is a query, and a mapper that queries is a
     * repository with a different name.
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
