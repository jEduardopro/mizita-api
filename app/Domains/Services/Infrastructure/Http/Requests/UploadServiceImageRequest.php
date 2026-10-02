<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Requests;

use App\Domains\Services\Application\Dtos\AttachServiceImageInput;
use Illuminate\Foundation\Http\FormRequest;

final class UploadServiceImageRequest extends FormRequest
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
                'mimetypes:'.implode(',', AttachServiceImageInput::ACCEPTED_MIME_TYPES),
                'max:'.intdiv(AttachServiceImageInput::MAXIMUM_BYTES, self::BYTES_PER_KILOBYTE),
                'dimensions:max_width='.self::MAXIMUM_DIMENSION_PIXELS.',max_height='.self::MAXIMUM_DIMENSION_PIXELS,
            ],
        ];
    }
}
