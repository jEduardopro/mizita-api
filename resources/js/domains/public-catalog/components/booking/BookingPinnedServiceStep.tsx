import { useTranslation } from 'react-i18next';
import { brandColorClasses } from '@/lib/booking-brand';
import type { PublicTeamMember } from '../../types';
import { bookingStepUrl } from './booking-steps';
import { BookingFlowLayout } from './BookingFlowLayout';
import { BookingServiceList } from './BookingServiceList';
import { BookingShareButton } from './BookingShareButton';
import { BookingStaffHeader } from './BookingStaffHeader';
import { BookingStaffUnavailable } from './BookingStaffUnavailable';
import type { ReadyBookingFlow } from './use-booking-flow';

type Props = {
    flow: ReadyBookingFlow;
    member: PublicTeamMember;
};

export function BookingPinnedServiceStep({ flow, member }: Props) {
    const { t } = useTranslation('public');

    const { page } = flow;

    const shareAction =
        member.booking_url === null ? undefined : (
            <BookingShareButton
                url={member.booking_url}
                title={t('booking.flow.pinned.shareTitle', {
                    name: member.name,
                    business: page.name,
                })}
            />
        );

    return (
        <BookingFlowLayout
            flow={flow}
            title={t('booking.flow.service.pinnedTitle')}
            actions={shareAction}
            intro={
                <BookingStaffHeader
                    name={member.name}
                    photoUrl={member.photo_url}
                    jobTitle={member.job_title}
                    about={member.about}
                    accent={brandColorClasses[page.brand.accent_color]}
                />
            }
        >
            <BookingServiceList
                slug={page.slug}
                services={flow.services}
                currencyCode={page.currency_code}
                selectedServiceId={flow.service?.id ?? null}
                accentColor={page.brand.accent_color}
                buttonShape={page.brand.button_shape}
                onSelect={(serviceId) => flow.advance({ service: serviceId })}
                emptyState={
                    <BookingStaffUnavailable
                        name={member.name}
                        allServicesUrl={bookingStepUrl(page.slug, 'service', {})}
                    />
                }
            />
        </BookingFlowLayout>
    );
}
