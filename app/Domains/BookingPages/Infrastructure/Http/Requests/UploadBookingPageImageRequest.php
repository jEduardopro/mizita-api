<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Http\Requests;

use App\Domains\BookingPages\Application\Dtos\AttachBookingPageImageInput;
use Illuminate\Foundation\Http\FormRequest;

final class UploadBookingPageImageRequest extends FormRequest
{
    private const BYTES_PER_KILOBYTE = 1024;

    private const MAXIMUM_DIMENSION_PIXELS = 4096;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimetypes:'.implode(',', AttachBookingPageImageInput::ACCEPTED_MIME_TYPES),
                'max:'.intdiv(AttachBookingPageImageInput::MAXIMUM_BYTES, self::BYTES_PER_KILOBYTE),
                'dimensions:max_width='.self::MAXIMUM_DIMENSION_PIXELS.',max_height='.self::MAXIMUM_DIMENSION_PIXELS,
            ],
        ];
    }
}
