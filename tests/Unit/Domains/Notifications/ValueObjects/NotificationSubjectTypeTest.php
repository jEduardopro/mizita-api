<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\TestCase;

uses(TestCase::class);

it('resolves every subject type to the model the morph map registers under it', function (NotificationSubjectType $type, string $model) {
    expect(Relation::getMorphedModel($type->value))->toBe($model);
})->with([
    'an appointment' => [NotificationSubjectType::Appointment, AppointmentModel::class],
    'a staff member' => [NotificationSubjectType::StaffMember, StaffMemberModel::class],
]);

it('leaves no subject type without a registered model', function () {
    $unregistered = array_values(array_filter(
        NotificationSubjectType::cases(),
        static fn (NotificationSubjectType $type): bool => Relation::getMorphedModel($type->value) === null,
    ));

    expect($unregistered)->toBe([]);
});
