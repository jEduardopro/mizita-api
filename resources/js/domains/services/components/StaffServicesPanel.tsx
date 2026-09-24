import { ListChecks } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { Service } from '../types';
import { StaffServiceRow } from './StaffServiceRow';

const SKELETON_ROWS = [0, 1, 2];

type Props = {
    services: Service[] | undefined;
    loadFailed: boolean;
    onRetry: () => void;
    emptyHint: string;
    renderAction?: (service: Service) => ReactNode;
    addField?: ReactNode;
};

function StaffServicesSkeleton() {
    return (
        <ul role="status" aria-busy="true" className="grid max-w-2xl gap-3">
            {SKELETON_ROWS.map((row) => (
                <li key={row}>
                    <Skeleton className="h-[4.25rem] rounded-xl" />
                </li>
            ))}
        </ul>
    );
}

export function StaffServicesPanel({
    services,
    loadFailed,
    onRetry,
    emptyHint,
    renderAction,
    addField,
}: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    if (loadFailed) {
        return (
            <div role="alert" className="grid max-w-md justify-items-start gap-3 py-2">
                <p className="text-sm font-medium">{t('staffServices.loadError')}</p>

                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        );
    }

    if (services === undefined) {
        return <StaffServicesSkeleton />;
    }

    return (
        <div className="grid max-w-2xl gap-4">
            {addField}

            {services.length === 0 ? (
                <div className="grid justify-items-start gap-2 rounded-xl border border-dashed border-border p-5">
                    <ListChecks aria-hidden="true" className="size-5 text-muted-foreground" />

                    <p className="text-sm font-medium">{t('staffServices.empty')}</p>

                    <p className="text-sm text-muted-foreground">{emptyHint}</p>
                </div>
            ) : (
                <ul className="grid gap-3">
                    {services.map((service) => (
                        <StaffServiceRow key={service.id} service={service} action={renderAction?.(service)} />
                    ))}
                </ul>
            )}
        </div>
    );
}
