<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Requests;

use App\Domains\Staff\Application\Dtos\InviteTeamMembersInput;
use App\Domains\Staff\Application\Dtos\TeamMemberInvitationInput;
use App\Domains\Staff\ValueObjects\InviteeEmail;
use App\Domains\Staff\ValueObjects\StaffRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteTeamMembersRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'min:1', 'max:'.InviteTeamMembersInput::MAXIMUM_MEMBERS],
            'members.*' => ['required', 'array'],
            'members.*.name' => ['required', 'string', 'max:'.TeamMemberInvitationInput::MAXIMUM_NAME_LENGTH],
            'members.*.email' => ['required', 'string', 'email', 'max:'.InviteeEmail::MAXIMUM_LENGTH],
            'members.*.level' => ['required', 'string', Rule::in(StaffRole::assignableValues())],
        ];
    }
}
