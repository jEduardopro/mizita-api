import { BookingPageLoadError } from '../BookingPageLoadError';
import { BookingFlowSkeleton } from './BookingFlowSkeleton';
import { BookingLinkInvalid } from './BookingLinkInvalid';
import type { BookingRecord } from './use-booking-record';

type Props = {
    record: BookingRecord;
};

export function BookingRecordFallback({ record }: Props) {
    if (record.status === 'invalid') {
        return <BookingLinkInvalid />;
    }

    if (record.status === 'error') {
        return <BookingPageLoadError onRetry={record.retry} />;
    }

    return <BookingFlowSkeleton />;
}
