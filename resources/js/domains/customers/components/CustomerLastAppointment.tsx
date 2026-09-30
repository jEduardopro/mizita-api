import { cn } from 'cn';
import { ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { serviceColorClasses, type ServiceColor } from '@/lib/service-color';
import { formatInstantTimeOfDay } from '@/lib/time';

const DETAIL_SEPARATOR = ' · ';

const DAY_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

type Props = {
    startsAt: string;
    timezone: string;
    serviceName: string;
    serviceColor: ServiceColor;
    staffName: string;
    onOpen: () => void;
};

function formatAppointmentDay(instant: string, timezone: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { ...DAY_FORMAT, timeZone: timezone }).format(
        new Date(instant),
    );
}

export function CustomerLastAppointment({
    startsAt,
    timezone,
    serviceName,
    serviceColor,
    staffName,
    onOpen,
}: Props) {
    const { t, i18n } = useTranslation('admin');

    const day = formatAppointmentDay(startsAt, timezone, i18n.language);
    const time = formatInstantTimeOfDay(startsAt, timezone);

    return (
        <button
            type="button"
            onClick={onOpen}
            className="-ml-2 flex min-h-11 min-w-0 items-center gap-2 justify-self-start rounded-lg px-2 py-1 text-left text-sm transition-colors outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 motion-reduce:transition-none"
        >
            <span className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
                <span className="text-muted-foreground">
                    {t('customers.show.lastAppointment.label')}
                </span>

                <span className="font-medium tabular-nums">{`${day}, ${time}`}</span>

                <span className="flex min-w-0 items-center gap-1.5 text-muted-foreground">
                    <span
                        aria-hidden="true"
                        className={cn('size-2 shrink-0 rounded-full', serviceColorClasses[serviceColor].bar)}
                    />

                    <span className="min-w-0 truncate">
                        {[serviceName, staffName].join(DETAIL_SEPARATOR)}
                    </span>
                </span>
            </span>

            <ChevronRight aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
        </button>
    );
}
