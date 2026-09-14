<?php

declare(strict_types=1);

namespace App\Domains\Phones\Application\UseCases;

use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\Dtos\PhoneData;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;

/**
 * An upsert, not an insert: an owner has at most one phone, enforced by a partial
 * unique index, so a second call has to move the existing record rather than
 * race the constraint with a new row.
 */
final class AttachPhone
{
    public function __construct(
        private readonly PhoneRepository $phones,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function handle(AttachPhoneInput $input): PhoneData
    {
        $phone = $this->phoneFor($input);

        $this->phones->save($phone);

        return PhoneData::fromEntity($phone);
    }

    private function phoneFor(AttachPhoneInput $input): Phone
    {
        $phone = $this->phones->findForOwner($input->ownerType, $input->ownerId);

        if ($phone === null) {
            return Phone::create(
                id: $this->ids->next(),
                ownerType: $input->ownerType,
                ownerId: $input->ownerId,
                number: $input->number,
                now: $this->clock->now(),
            );
        }

        $phone->changeNumber($input->number);

        return $phone;
    }
}
