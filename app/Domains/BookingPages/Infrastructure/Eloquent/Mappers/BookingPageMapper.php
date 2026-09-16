<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Eloquent\Mappers;

use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use DateTimeImmutable;

final class BookingPageMapper
{
    public function toEntity(BookingPageModel $model, string $businessId): BookingPage
    {
        return BookingPage::restore(
            id: $model->uuid,
            businessId: $businessId,
            accentColor: $model->accent_color,
            buttonShape: $model->button_shape,
            theme: $model->theme,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(BookingPage $page, int $businessKey): array
    {
        return [
            'uuid' => $page->id,
            'business_id' => $businessKey,
            'accent_color' => $page->accentColor()->value,
            'button_shape' => $page->buttonShape()->value,
            'theme' => $page->theme()->value,
        ];
    }
}
