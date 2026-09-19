<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Appointments\Application\Dtos\BookAppointmentAsGuestInput;
use App\Domains\Appointments\Application\Dtos\CancelGuestBookingInput;
use App\Domains\Appointments\Application\Dtos\GuestBookingCredentials;
use App\Domains\Appointments\Application\Dtos\GuestDetailsInput;
use App\Domains\Appointments\Application\Dtos\RescheduleGuestBookingInput;
use App\Domains\Appointments\Application\UseCases\BookAppointmentAsGuest;
use App\Domains\Appointments\Application\UseCases\CancelGuestBooking;
use App\Domains\Appointments\Application\UseCases\RescheduleGuestBooking;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Infrastructure\Mappers\GuestBookingMapper;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;

final class AppointmentsGuestBookingDesk implements GuestBookingDesk
{
    public function __construct(
        private readonly BookAppointmentAsGuest $bookAppointmentAsGuest,
        private readonly RescheduleGuestBooking $rescheduleGuestBooking,
        private readonly CancelGuestBooking $cancelGuestBooking,
        private readonly GuestBookingMapper $mapper,
    ) {}

    public function book(string $businessId, PublicBookingRequest $request): PublicGuestBookingConfirmation
    {
        $response = $this->bookAppointmentAsGuest->handle(new BookAppointmentAsGuestInput(
            businessId: $businessId,
            serviceId: $request->serviceId,
            staffMemberId: $request->staffMemberId,
            startsAt: $request->startsAt,
            guest: new GuestDetailsInput(
                name: $request->guest->name,
                email: $request->guest->email,
                phoneCountryCode: $request->guest->phoneCountryCode,
                phoneNationalNumber: $request->guest->phoneNationalNumber,
            ),
            notes: $request->notes,
        ));

        $confirmation = $response->value();

        return new PublicGuestBookingConfirmation(
            booking: $this->mapper->toPublicBooking($confirmation->booking),
            manageToken: $confirmation->manageToken,
        );
    }

    public function reschedule(
        string $businessId,
        PublicBookingCredentials $credentials,
        string $startsAt,
    ): PublicGuestBooking {
        $response = $this->rescheduleGuestBooking->handle(new RescheduleGuestBookingInput(
            businessId: $businessId,
            credentials: $this->credentialsFor($credentials),
            startsAt: $startsAt,
        ));

        return $this->mapper->toPublicBooking($response->value());
    }

    public function cancel(string $businessId, PublicBookingCredentials $credentials): PublicGuestBooking
    {
        $response = $this->cancelGuestBooking->handle(new CancelGuestBookingInput(
            businessId: $businessId,
            credentials: $this->credentialsFor($credentials),
        ));

        return $this->mapper->toPublicBooking($response->value());
    }

    private function credentialsFor(PublicBookingCredentials $credentials): GuestBookingCredentials
    {
        return new GuestBookingCredentials(
            referenceCode: $credentials->referenceCode,
            manageToken: $credentials->manageToken,
        );
    }
}
