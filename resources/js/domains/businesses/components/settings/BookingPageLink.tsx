import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { bookingPageUrl } from './booking-page-url';

type Props = {
    slug: string;
    className?: string;
    children: ReactNode;
};

export function BookingPageLink({ slug, className, children }: Props) {
    if (slug === '') {
        return (
            <Button type="button" variant="ghost" disabled className={className}>
                {children}
            </Button>
        );
    }

    return (
        <Button asChild variant="ghost" className={className}>
            <a href={bookingPageUrl(slug)} target="_blank" rel="noopener noreferrer">
                {children}
            </a>
        </Button>
    );
}
