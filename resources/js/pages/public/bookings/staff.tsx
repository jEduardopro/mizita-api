import { useTranslation } from 'react-i18next';
import { BookingFlowFallback } from '@/domains/public-catalog/components/booking/BookingFlowFallback';
import { BookingFlowLayout } from '@/domains/public-catalog/components/booking/BookingFlowLayout';
import { BookingStaffList } from '@/domains/public-catalog/components/booking/BookingStaffList';
import { useBookingFlow } from '@/domains/public-catalog/components/booking/use-booking-flow';
import { brandColorClasses } from '@/lib/booking-brand';

type Props = {
    slug: string;
};

export default function BookingStaffStep({ slug }: Props) {
    const { t } = useTranslation('public');
    const flow = useBookingFlow(slug, 'staff');

    if (flow.status !== 'ready') {
        return <BookingFlowFallback flow={flow} />;
    }

    const { page, service } = flow;

    const eligibleTeam =
        service === null
            ? []
            : page.team.filter((member) => service.staff_ids.includes(member.id));

    return (
        <BookingFlowLayout
            flow={flow}
            title={t('booking.flow.staff.title')}
            description={t('booking.flow.staff.description')}
        >
            <BookingStaffList
                team={eligibleTeam}
                selectedStaffId={flow.staffMember?.id ?? null}
                accent={brandColorClasses[page.brand.accent_color]}
                buttonShape={page.brand.button_shape}
                onSelect={(staffId) => flow.goTo('time', { staff: staffId })}
            />
        </BookingFlowLayout>
    );
}
