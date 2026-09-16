<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Eloquent\Factories;

use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPageModel>
 */
final class BookingPageModelFactory extends Factory
{
    protected $model = BookingPageModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'accent_color' => BookingPage::DEFAULT_ACCENT_COLOR->value,
            'button_shape' => BookingPage::DEFAULT_BUTTON_SHAPE->value,
            'theme' => BookingPage::DEFAULT_THEME->value,
        ];
    }
}
