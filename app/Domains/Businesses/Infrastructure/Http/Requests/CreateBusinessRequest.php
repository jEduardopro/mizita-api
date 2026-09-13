<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use App\Shared\ValueObjects\CountryCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * There is no slug field, on purpose: the address is derived from the name, so
 * accepting one would give the same fact two sources that can disagree.
 *
 * There is no owner field either. The owner is the authenticated caller, and
 * the controller reads it from the session.
 */
final class CreateBusinessRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // all_with_bc, not all: the client sends whatever
            // Intl.DateTimeFormat().resolvedOptions().timeZone reports, and
            // real browsers still report backward compatible aliases such as
            // Asia/Calcutta. "all" excludes those, so the stricter rule would
            // reject a zone that works, at signup, for no gain. The time zone
            // is the only source of local time for every hour this business
            // will publish, so what must not get in is a string DateTimeZone
            // cannot resolve at all - which this still keeps out.
            'timezone' => ['required', 'string', 'timezone:all_with_bc'],
            'industry_id' => ['required', 'uuid'],
            // Optional at signup: an owner can add a number later. Sent as an
            // object because a country and a national number are two facts, and
            // guessing the dial code from the digits is how numbers get lost.
            'phone' => ['sometimes', 'nullable', 'array'],
            'phone.country_code' => ['required_with:phone', Rule::enum(CountryCode::class)],
            'phone.national_number' => ['required_with:phone', 'string', 'regex:/^\d{7,15}$/'],
        ];
    }
}
