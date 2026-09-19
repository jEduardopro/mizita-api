import { useTranslation } from 'react-i18next';
import type { PublicService, PublicTeamMember } from '../../types';
import { bookingSummaryLines } from './booking-summary-lines';

type Props = {
    service: PublicService | null;
    staffMember: PublicTeamMember | null;
    startsAt: string | null;
    timezone: string;
    currencyCode: string;
};

export function BookingSummary({
    service,
    staffMember,
    startsAt,
    timezone,
    currencyCode,
}: Props) {
    const { t, i18n } = useTranslation('public');

    const lines = bookingSummaryLines({
        service,
        staffMember,
        startsAt,
        timezone,
        currencyCode,
        locale: i18n.language,
        t,
    });

    if (lines.length === 0) {
        return null;
    }

    return (
        <section
            aria-label={t('booking.flow.summary.title')}
            className="lg:rounded-2xl lg:border lg:border-border lg:bg-card lg:p-5 lg:text-card-foreground lg:shadow-sm"
        >
            <h2 className="sr-only lg:not-sr-only lg:mb-4 lg:font-heading lg:text-sm lg:font-semibold lg:tracking-[-0.01em]">
                {t('booking.flow.summary.title')}
            </h2>

            <dl className="flex flex-wrap gap-2 lg:grid lg:gap-3.5">
                {lines.map((line) => (
                    <div
                        key={line.id}
                        className="min-w-0 rounded-full border border-border bg-muted/40 px-3 py-1.5 lg:rounded-none lg:border-0 lg:bg-transparent lg:px-0 lg:py-0"
                    >
                        <dt className="sr-only lg:not-sr-only lg:text-[0.6875rem] lg:font-medium lg:tracking-[0.1em] lg:text-muted-foreground lg:uppercase">
                            {line.label}
                        </dt>

                        <dd className="text-xs lg:mt-0.5 lg:text-sm">{line.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
