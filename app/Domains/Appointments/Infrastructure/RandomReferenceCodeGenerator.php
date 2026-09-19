<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure;

use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\ValueObjects\ReferenceCode;

final class RandomReferenceCodeGenerator implements ReferenceCodeGenerator
{
    public function next(): ReferenceCode
    {
        $lastPosition = strlen(ReferenceCode::ALPHABET) - 1;
        $code = '';

        for ($character = 0; $character < ReferenceCode::LENGTH; $character++) {
            $code .= ReferenceCode::ALPHABET[random_int(0, $lastPosition)];
        }

        return ReferenceCode::fromString($code);
    }
}
