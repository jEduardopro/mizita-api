<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Http\Requests;

use App\Domains\BookingPages\Application\UseCases\AddBookingPageGalleryImage;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderBookingPageGalleryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.AddBookingPageGalleryImage::MAXIMUM_GALLERY_IMAGES],
            'images.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
