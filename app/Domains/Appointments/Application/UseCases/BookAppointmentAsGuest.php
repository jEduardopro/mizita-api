<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\BookAppointmentAsGuestInput;
use App\Domains\Appointments\Application\Dtos\GuestBookingConfirmationData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Contracts\ManageTokenFactory;
use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\Exceptions\AppointmentSlotNotBookable;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Domains\Appointments\ValueObjects\ManageTokenExpiry;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final class BookAppointmentAsGuest
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly ServiceCatalog $services,
        private readonly CustomerDirectory $customers,
        private readonly BookableSlots $slots,
        private readonly CancellationPolicy $policies,
        private readonly GuestBookingPresenter $presenter,
        private readonly ReferenceCodeGenerator $referenceCodes,
        private readonly ManageTokenFactory $manageTokens,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<GuestBookingConfirmationData>
     */
    public function handle(BookAppointmentAsGuestInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $now = $this->clock->now();
            $slot = $this->bookableSlotFor($input);
            $manageToken = $this->manageTokens->issue();

            $appointment = $this->transactions->run(
                fn (): Appointment => $this->register($input, $slot, $manageToken, $now),
            );

            $confirmation = new GuestBookingConfirmationData(
                $this->presenter->describe(
                    $input->businessId,
                    $appointment,
                    $this->policies->forBusiness($input->businessId),
                    $now,
                ),
                $manageToken->value,
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new AppointmentBooked($appointment->id));

        return UseCaseResponse::success($confirmation);
    }

    /**
     * @throws AppointmentSlotNotBookable
     */
    private function bookableSlotFor(BookAppointmentAsGuestInput $input): AppointmentSlot
    {
        $startsAt = $input->toStartsAt();

        $bookable = $this->slots->isBookable(
            $input->businessId,
            $input->serviceId,
            $input->staffMemberId,
            $startsAt,
        );

        if (! $bookable) {
            throw AppointmentSlotNotBookable::startingAt($startsAt);
        }

        return AppointmentSlot::lasting(
            $startsAt,
            $this->services->describe($input->businessId, $input->serviceId)->durationMinutes,
        );
    }

    /**
     * @throws AppointmentOverlaps
     */
    private function register(
        BookAppointmentAsGuestInput $input,
        AppointmentSlot $slot,
        ManageToken $manageToken,
        DateTimeImmutable $now,
    ): Appointment {
        $customer = $this->customers->findOrCreateGuest($input->businessId, $input->guest->toContact());

        $appointment = Appointment::bookAsGuest(
            id: $this->ids->next(),
            businessId: $input->businessId,
            customerId: $customer->id,
            serviceId: $input->serviceId,
            staffMemberId: $input->staffMemberId,
            slot: $slot,
            notes: $input->toNotes(),
            referenceCode: $this->referenceCodes->next(),
            manageTokenHash: $manageToken->hash(),
            manageTokenExpiresAt: ManageTokenExpiry::forSlot($slot),
            now: $now,
        );

        $this->appointments->save($appointment);

        return $appointment;
    }
}
