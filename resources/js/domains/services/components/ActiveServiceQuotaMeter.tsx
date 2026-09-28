import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { PLAN_SETTINGS_HREF } from '@/lib/plan';
import type { ActiveServiceAllowance } from './use-active-service-allowance';

type Props = {
    allowance: ActiveServiceAllowance;
};

export function ActiveServiceQuotaMeter({ allowance }: Props) {
    const { t } = useTranslation('admin');

    if (allowance.status === 'loading') {
        return <Skeleton role="status" aria-busy="true" className="h-5 w-48 rounded-md" />;
    }

    if (allowance.status !== 'limited') {
        return null;
    }

    const slots = Array.from({ length: allowance.limit }, (_, slot) => slot);

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span aria-hidden="true" className="flex gap-1">
                {slots.map((slot) => (
                    <span
                        key={slot}
                        className={cn(
                            'h-1.5 w-5 rounded-full',
                            slot < allowance.activeCount ? 'bg-foreground' : 'bg-border',
                        )}
                    />
                ))}
            </span>

            <p className="text-muted-foreground">
                {t('plan.services.quota', {
                    count: allowance.activeCount,
                    limit: allowance.limit,
                })}
            </p>

            {allowance.allowsAnother ? null : (
                <Link
                    href={PLAN_SETTINGS_HREF}
                    className="inline-flex min-h-11 items-center rounded-md font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 md:min-h-9"
                >
                    {t('plan.notice.link')}
                </Link>
            )}
        </div>
    );
}
