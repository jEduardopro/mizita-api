<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape only: presence, type, structure and length bounds. Whether a value is
 * one the business will accept is a judgement that belongs to the use case, so
 * it is made once, inside the domain, where a console command or a queued job
 * gets it too.
 *
 * There is no slug field, on purpose - the address is derived from the name - and
 * no owner field: the owner is the authenticated caller, read from the session.
 */
final class CreateBusinessRequest extends FormRequest
{
    /** ISO 3166-1 alpha-2, so two characters exactly - a shape, not a country list. */
    private const COUNTRY_CODE_LENGTH = 2;

    /** Enough for the longest E.164 number plus the separators people type. */
    private const MAX_NATIONAL_NUMBER_LENGTH = 24;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // all_with_bc, not all: the client sends whatever
            // Intl.DateTimeFormat().resolvedOptions().timeZone reports, and real
            // browsers still report backward compatible aliases such as
            // Asia/Calcutta, which "all" excludes.
            'timezone' => ['required', 'string', 'timezone:all_with_bc'],
            'industry_id' => ['required', 'uuid'],
            // An object because a country and a national number are two facts,
            // and guessing the dial code from the digits is how numbers get lost.
            'phone' => ['sometimes', 'nullable', 'array'],
            // No Rule::enum here. Which countries the platform operates in is a
            // business decision, and the use case has to rule on the string
            // anyway - it cannot trust one that reached it through a queue.
            'phone.country_code' => [
                'required_with:phone',
                'string',
                'size:'.self::COUNTRY_CODE_LENGTH,
            ],
            'phone.national_number' => [
                'required_with:phone',
                'string',
                'max:'.self::MAX_NATIONAL_NUMBER_LENGTH,
            ],
        ];
    }
}
