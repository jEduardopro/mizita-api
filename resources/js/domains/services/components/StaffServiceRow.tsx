import { cn } from 'cn';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { serviceColorClasses } from '@/lib/service-color';
import { formatServiceSummary } from '@/lib/service-format';
import type { Service } from '../types';

type Props = {
    service: Service;
    action?: ReactNode;
};

export function StaffServiceRow({ service, action }: Props) {
    const { t, i18n } = useTranslation('admin');

    return (
        <li className="relative flex items-center gap-3 overflow-hidden rounded-xl border border-border bg-card py-2.5 pr-2.5 pl-4">
            <span
                aria-hidden="true"
                className={cn('absolute inset-y-0 left-0 w-1', serviceColorClasses[service.color].bar)}
            />

            <ServiceColorTile color={service.color} imageUrl={service.image_url} className="size-10" />

            <div className="grid min-w-0 flex-1 gap-0.5">
                <p className="truncate text-sm font-medium">{service.name}</p>

                <p className="truncate text-xs text-muted-foreground">
                    {formatServiceSummary(service, i18n.language, t)}
                </p>
            </div>

            {action}
        </li>
    );
}
