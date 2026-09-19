import { useTranslation } from 'react-i18next';
import { BookingConfirmationCard } from '@/domains/public-catalog/components/booking/BookingConfirmationCard';
import { BookingManageActions } from '@/domains/public-catalog/components/booking/BookingManageActions';
import { BookingOutcomeLayout } from '@/domains/public-catalog/components/booking/BookingOutcomeLayout';
import { BookingRecordFallback } from '@/domains/public-catalog/components/booking/BookingRecordFallback';
import { useBookingRecord } from '@/domains/public-catalog/components/booking/use-booking-record';

type Props = {
    slug: string;
    reference: string;
};

export default function BookingManageScreen({ slug, reference }: Props) {
    const { t } = useTranslation('public');
    const record = useBookingRecord(slug, reference);

    if (record.status !== 'ready') {
        return <BookingRecordFallback record={record} />;
    }

    const { page, booking, credentials } = record;

    return (
        <BookingOutcomeLayout page={page} title={t('booking.manage.title')}>
            <h1 className="font-heading text-[clamp(1.5rem,5vw,2rem)] leading-tight font-semibold tracking-[-0.02em] text-balance">
                {t('booking.manage.title')}
            </h1>

            <BookingConfirmationCard booking={booking} timezone={page.timezone}>
                <BookingManageActions page={page} booking={booking} credentials={credentials} />
            </BookingConfirmationCard>
        </BookingOutcomeLayout>
    );
}
