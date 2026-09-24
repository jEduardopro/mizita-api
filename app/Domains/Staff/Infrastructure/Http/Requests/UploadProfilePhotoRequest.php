<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Requests;

use App\Domains\Staff\Application\Dtos\ReplaceMyProfilePhotoInput;
use Illuminate\Foundation\Http\FormRequest;

final class UploadProfilePhotoRequest extends FormRequest
{
    private const BYTES_PER_KILOBYTE = 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'mimetypes:'.implode(',', ReplaceMyProfilePhotoInput::ACCEPTED_MIME_TYPES),
                'max:'.intdiv(ReplaceMyProfilePhotoInput::MAXIMUM_BYTES, self::BYTES_PER_KILOBYTE),
            ],
        ];
    }
}
