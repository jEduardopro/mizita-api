import { useTranslation } from 'react-i18next';
import { BookingFlowFallback } from '@/domains/public-catalog/components/booking/BookingFlowFallback';
import { BookingFlowLayout } from '@/domains/public-catalog/components/booking/BookingFlowLayout';
import { BookingPinnedServiceStep } from '@/domains/public-catalog/components/booking/BookingPinnedServiceStep';
import { BookingServiceList } from '@/domains/public-catalog/components/booking/BookingServiceList';
import { useBookingFlow } from '@/domains/public-catalog/components/booking/use-booking-flow';

type Props = {
    slug: string;
};

export default function BookingServiceStep({ slug }: Props) {
    const { t } = useTranslation('public');
    const flow = useBookingFlow(slug, 'service');

    if (flow.status !== 'ready') {
        return <BookingFlowFallback flow={flow} />;
    }

    if (flow.pinnedMember !== null) {
        return <BookingPinnedServiceStep flow={flow} member={flow.pinnedMember} />;
    }

    const { page } = flow;

    return (
        <BookingFlowLayout
            flow={flow}
            title={t('booking.flow.service.title')}
            description={t('booking.flow.service.description')}
        >
            <BookingServiceList
                slug={page.slug}
                services={flow.services}
                currencyCode={page.currency_code}
                selectedServiceId={flow.service?.id ?? null}
                accentColor={page.brand.accent_color}
                buttonShape={page.brand.button_shape}
                onSelect={(serviceId) => flow.advance({ service: serviceId })}
            />
        </BookingFlowLayout>
    );
}
