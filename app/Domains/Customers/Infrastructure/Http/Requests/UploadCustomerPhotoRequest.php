<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Requests;

use App\Domains\Customers\Application\Dtos\AttachCustomerPhotoInput;
use Illuminate\Foundation\Http\FormRequest;

final class UploadCustomerPhotoRequest extends FormRequest
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
                'mimetypes:'.implode(',', AttachCustomerPhotoInput::ACCEPTED_MIME_TYPES),
                'max:'.intdiv(AttachCustomerPhotoInput::MAXIMUM_BYTES, self::BYTES_PER_KILOBYTE),
            ],
        ];
    }
}
