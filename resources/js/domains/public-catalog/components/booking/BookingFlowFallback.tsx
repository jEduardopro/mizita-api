import { BookingPageLoadError } from '../BookingPageLoadError';
import { BookingFlowSkeleton } from './BookingFlowSkeleton';
import type { BookingFlow } from './use-booking-flow';

type Props = {
    flow: BookingFlow;
};

export function BookingFlowFallback({ flow }: Props) {
    if (flow.status === 'error') {
        return <BookingPageLoadError onRetry={flow.retry} />;
    }

    return <BookingFlowSkeleton />;
}
