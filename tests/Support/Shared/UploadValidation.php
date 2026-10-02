<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

final class UploadValidation
{
    /**
     * @return list<string>
     */
    public static function failedRules(FormRequest $request, string $field, string $sourcePath, string $clientName = 'upload.png'): array
    {
        $validator = (new Factory(new Translator(new ArrayLoader, 'en')))->make(
            [$field => new UploadedFile($sourcePath, $clientName, null, null, true)],
            $request->rules(),
        );

        $validator->passes();

        return array_keys($validator->failed()[$field] ?? []);
    }
}
