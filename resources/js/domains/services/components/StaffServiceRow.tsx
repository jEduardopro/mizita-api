import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { formatServiceSummary } from '@/lib/service-format';
import type { Service } from '../types';

type Props = {
    service: Service;
    action?: ReactNode;
};

export function StaffServiceRow({ service, action }: Props) {
    const { t, i18n } = useTranslation('admin');

    return (
        <li className="flex items-center gap-3 border-b border-border py-3 last:border-b-0">
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
