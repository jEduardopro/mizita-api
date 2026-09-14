<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Shared\Application\Warning;

final readonly class WarningEnvelope
{
    private const TRANSLATION_PREFIX = 'messages.warnings.';

    /**
     * @param  list<Warning>  $warnings
     * @return array{warnings?: list<array{code: string, message: string}>}
     */
    public static function for(array $warnings): array
    {
        if ($warnings === []) {
            return [];
        }

        return ['warnings' => array_map(self::describe(...), $warnings)];
    }

    /**
     * @return array{code: string, message: string}
     */
    private static function describe(Warning $warning): array
    {
        return [
            'code' => $warning->code,
            'message' => (string) __(self::TRANSLATION_PREFIX.$warning->code),
        ];
    }
}
