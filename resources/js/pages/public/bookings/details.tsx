import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { bookingConfirmedUrl } from '@/domains/public-catalog/components/booking/booking-steps';
import { BookingDetailsForm } from '@/domains/public-catalog/components/booking/BookingDetailsForm';
import { BookingFlowFallback } from '@/domains/public-catalog/components/booking/BookingFlowFallback';
import { BookingFlowLayout } from '@/domains/public-catalog/components/booking/BookingFlowLayout';
import { BookingPolicyPanel } from '@/domains/public-catalog/components/booking/BookingPolicyPanel';
import { useBookingFlow } from '@/domains/public-catalog/components/booking/use-booking-flow';
import { publicCatalogKeys, useCreatePublicBooking } from '@/domains/public-catalog/queries';
import type { PublicGuestPayload } from '@/domains/public-catalog/types';
import { formMessageFrom, httpStatusFrom } from '@/lib/http';
import { raiseErrorToast } from '@/lib/toast';

const SLOT_TAKEN_STATUS = 409;

type Props = {
    slug: string;
};

export default function BookingDetailsStep({ slug }: Props) {
    const { t } = useTranslation('public');
    const flow = useBookingFlow(slug, 'details');
    const createBooking = useCreatePublicBooking(slug);
    const queryClient = useQueryClient();

    if (flow.status !== 'ready') {
        return <BookingFlowFallback flow={flow} />;
    }

    const { page, service, staffMember, startsAt } = flow;

    if (service === null || staffMember === null || startsAt === null) {
        return <BookingFlowFallback flow={flow} />;
    }

    const chosenSlot = {
        service_id: service.id,
        staff_member_id: staffMember.id,
        starts_at: startsAt,
    };

    function returnToTimeStep(error: unknown): void {
        raiseErrorToast(formMessageFrom(error, t('booking.flow.errors.booking')));

        void queryClient.invalidateQueries({ queryKey: publicCatalogKeys.availabilities(slug) });

        flow.goTo('time', { at: null });
    }

    async function submitBooking(guest: PublicGuestPayload, notes: string | null): Promise<void> {
        try {
            const confirmation = await createBooking.mutateAsync({
                ...chosenSlot,
                guest,
                notes,
            });

            router.visit(
                bookingConfirmedUrl(
                    slug,
                    confirmation.booking.reference_code,
                    confirmation.manage_token,
                ),
                { replace: true },
            );
        } catch (error) {
            if (httpStatusFrom(error) !== SLOT_TAKEN_STATUS) {
                throw error;
            }

            returnToTimeStep(error);
        }
    }

    return (
        <BookingFlowLayout
            flow={flow}
            title={t('booking.flow.details.title')}
            description={t('booking.flow.details.description')}
        >
            <BookingDetailsForm
                accentColor={page.brand.accent_color}
                buttonShape={page.brand.button_shape}
                isSubmitting={createBooking.isPending}
                onSubmit={submitBooking}
            />

            {page.booking_policy === undefined ? null : (
                <BookingPolicyPanel message={page.booking_policy.policy_message} />
            )}
        </BookingFlowLayout>
    );
}
