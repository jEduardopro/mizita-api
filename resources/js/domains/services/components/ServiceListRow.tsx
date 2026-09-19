import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { Badge } from '@/components/ui/badge';
import { serviceColorClasses } from '@/lib/service-color';
import { formatBuffer, formatDuration, formatPrice } from '@/lib/service-format';
import type { Service } from '../types';
import { ServiceRowActions } from './ServiceRowActions';
import { serviceEditUrl } from './service-urls';
import { ServiceStaffAvatars } from './ServiceStaffAvatars';

type Props = {
    service: Service;
};

export function ServiceListRow({ service }: Props) {
    const { t, i18n } = useTranslation('admin');

    const duration = formatDuration(service.duration_minutes, t);
    const price = formatPrice(service.price, i18n.language, t);
    const summary =
        service.buffer_minutes > 0
            ? t('services.summaryWithBuffer', {
                  duration,
                  buffer: formatBuffer(service.buffer_minutes, t),
                  price,
              })
            : t('services.summary', { duration, price });

    return (
        <article className="relative flex items-center gap-3 overflow-hidden rounded-xl border border-border bg-card py-2.5 pr-2.5 pl-4">
            <span
                aria-hidden="true"
                className={cn(
                    'absolute inset-y-0 left-0 w-1',
                    serviceColorClasses[service.color].bar,
                )}
            />

            <ServiceColorTile color={service.color} imageUrl={service.image_url} />

            <div className="grid min-w-0 flex-1 gap-0.5">
                <div className="flex min-w-0 items-center gap-2">
                    <Link
                        href={serviceEditUrl(service.id)}
                        className="truncate text-sm font-medium outline-none hover:underline focus-visible:underline"
                    >
                        {service.name}
                    </Link>

                    {service.active ? null : (
                        <Badge variant="outline" className="shrink-0">
                            {t('services.hidden')}
                        </Badge>
                    )}
                </div>

                <p className="truncate text-xs text-muted-foreground">
                    {summary}

                    {service.staff.length > 0 ? (
                        <span className="sm:hidden">
                            {' · '}
                            {t('services.staffCount', { count: service.staff.length })}
                        </span>
                    ) : null}
                </p>
            </div>

            <div className="hidden sm:block">
                <ServiceStaffAvatars staff={service.staff} />
            </div>

            <ServiceRowActions service={service} />
        </article>
    );
}
