<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Mappers;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use DateTimeImmutable;

/**
 * Translates between the persistence model and the domain entity. Only the
 * repository adapter uses it.
 */
final class BusinessMapper
{
    public function toEntity(BusinessModel $model): Business
    {
        return Business::restore(
            // The uuid is the domain identity; the int primary key stays here.
            id: $model->uuid,
            name: $model->name,
            slug: $model->slug,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Business $business): array
    {
        return [
            'uuid' => $business->id,
            'name' => $business->name(),
            'slug' => $business->slug(),
        ];
    }
}
