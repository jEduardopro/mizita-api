<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use App\Domains\Businesses\Application\Dtos\AttachBusinessLogoInput;
use Illuminate\Foundation\Http\FormRequest;

final class UploadBusinessLogoRequest extends FormRequest
{
    private const BYTES_PER_KILOBYTE = 1024;

    private const MAXIMUM_DIMENSION_PIXELS = 4096;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'mimetypes:'.implode(',', AttachBusinessLogoInput::ACCEPTED_MIME_TYPES),
                'max:'.intdiv(AttachBusinessLogoInput::MAXIMUM_BYTES, self::BYTES_PER_KILOBYTE),
                'dimensions:max_width='.self::MAXIMUM_DIMENSION_PIXELS.',max_height='.self::MAXIMUM_DIMENSION_PIXELS,
            ],
        ];
    }
}
