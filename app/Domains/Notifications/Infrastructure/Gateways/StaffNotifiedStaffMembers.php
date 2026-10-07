<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Gateways;

use App\Domains\Notifications\Contracts\NotifiedStaffMembers;
use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;
use Illuminate\Support\Facades\DB;
use stdClass;

final class StaffNotifiedStaffMembers implements NotifiedStaffMembers
{
    private const STAFF_MEMBERS_TABLE = 'staff_members';

    private const SELECTED_COLUMNS = [
        'staff_members.uuid as staff_member_id',
        'users.name as name',
    ];

    public function describe(string $businessId, string $staffMemberId): NotifiedStaffMember
    {
        $row = DB::table(self::STAFF_MEMBERS_TABLE)
            ->join('businesses', 'businesses.id', '=', 'staff_members.business_id')
            ->join('users', 'users.id', '=', 'staff_members.account_id')
            ->where('businesses.uuid', $businessId)
            ->where('staff_members.uuid', $staffMemberId)
            ->first(self::SELECTED_COLUMNS);

        if (! $row instanceof stdClass) {
            throw NotifiedStaffMemberNotFound::inBusiness($businessId, $staffMemberId);
        }

        return new NotifiedStaffMember(
            staffMemberId: (string) $row->staff_member_id,
            name: (string) $row->name,
        );
    }
}
