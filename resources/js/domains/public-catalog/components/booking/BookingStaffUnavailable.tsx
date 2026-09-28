import { Link } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

type Props = {
    name: string;
    allServicesUrl: string;
};

export function BookingStaffUnavailable({ name, allServicesUrl }: Props) {
    const { t } = useTranslation('public');

    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <CalendarX2 aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-heading text-base font-semibold tracking-[-0.01em]">
                {t('booking.flow.pinned.unavailable.title')}
            </p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('booking.flow.pinned.unavailable.description', { name })}
            </p>

            <Button asChild variant="outline" className="mt-1 h-11 px-5">
                <Link href={allServicesUrl}>{t('booking.flow.pinned.unavailable.action')}</Link>
            </Button>
        </div>
    );
}
