import { BookingPage } from '@/domains/public-catalog/components/BookingPage';
import { BookingPageLoadError } from '@/domains/public-catalog/components/BookingPageLoadError';
import { BookingPageSkeleton } from '@/domains/public-catalog/components/BookingPageSkeleton';
import { usePublicBusinessPage } from '@/domains/public-catalog/queries';

type Props = {
    slug: string;
    serviceSlug?: string;
};

export default function BusinessBookingPage({ slug, serviceSlug }: Props) {
    const { data, isPending, isError, refetch } = usePublicBusinessPage(slug);

    if (isPending) {
        return <BookingPageSkeleton />;
    }

    if (isError) {
        return <BookingPageLoadError onRetry={() => void refetch()} />;
    }

    return <BookingPage page={data} openServiceSlug={serviceSlug ?? null} />;
}
