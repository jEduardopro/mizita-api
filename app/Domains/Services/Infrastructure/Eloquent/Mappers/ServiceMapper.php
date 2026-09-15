<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Eloquent\Mappers;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\Slug;
use DateTimeImmutable;

final class ServiceMapper
{
    public function toEntity(ServiceModel $model, string $businessId): Service
    {
        return Service::restore(
            id: $model->uuid,
            businessId: $businessId,
            name: $model->name,
            slug: Slug::restore($model->slug),
            description: $model->description,
            duration: Duration::restore($model->duration_minutes),
            buffer: Buffer::restore($model->buffer_minutes),
            price: Price::restore($model->price),
            color: $model->color,
            active: $model->active,
            staffIds: $this->staffIdsOf($model),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Service $service, int $businessKey): array
    {
        return [
            'uuid' => $service->id,
            'business_id' => $businessKey,
            'name' => $service->name(),
            'slug' => $service->slug(),
            'description' => $service->description(),
            'duration_minutes' => $service->durationMinutes(),
            'buffer_minutes' => $service->bufferMinutes(),
            'price' => $service->price(),
            'color' => $service->color()->value,
            'active' => $service->isActive(),
        ];
    }

    /**
     * @return list<string>
     */
    private function staffIdsOf(ServiceModel $model): array
    {
        return $model->staffMembers->pluck('uuid')->values()->all();
    }
}
