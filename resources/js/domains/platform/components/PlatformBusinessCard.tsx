import { useTranslation } from 'react-i18next';
import type { PlatformBusiness } from '../types';
import { ImpersonateOwnerButton } from './ImpersonateOwnerButton';
import { PlatformBusinessOwner } from './PlatformBusinessOwner';
import { PlatformPlanBadge } from './PlatformPlanBadge';

type Props = {
    business: PlatformBusiness;
    createdAt: string;
};

export function PlatformBusinessCard({ business, createdAt }: Props) {
    const { t } = useTranslation('platform');

    return (
        <article className="grid gap-3 rounded-xl border border-border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="grid min-w-0 leading-tight">
                    <h2 className="truncate text-sm font-medium">{business.name}</h2>
                    <p className="truncate text-xs text-muted-foreground">/{business.slug}</p>
                </div>

                <PlatformPlanBadge plan={business.plan} />
            </div>

            <PlatformBusinessOwner owner={business.owner} />

            <p className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground tabular-nums">
                <time dateTime={business.created_at}>
                    {t('businesses.createdOn', { date: createdAt })}
                </time>
                <span>{t('businesses.servicesCount', { count: business.services_count })}</span>
                <span>{t('businesses.customersCount', { count: business.customers_count })}</span>
            </p>

            {business.owner === null ? null : (
                <ImpersonateOwnerButton
                    businessId={business.id}
                    businessName={business.name}
                    ownerName={business.owner.name}
                    label={t('businesses.impersonate.action')}
                    className="h-11 w-full"
                />
            )}
        </article>
    );
}
