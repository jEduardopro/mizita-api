import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { AdminBreadcrumbs, type Breadcrumb } from '@/components/admin/shell/AdminBreadcrumbs';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';

const MINIMUM_TRAIL_LENGTH = 2;

type Props = {
    title: string;
    breadcrumbs?: Breadcrumb[];
    actions?: ReactNode;
};

export function AdminHeader({ title, breadcrumbs, actions }: Props) {
    const { t } = useTranslation('admin');

    const showsTrail = breadcrumbs !== undefined && breadcrumbs.length >= MINIMUM_TRAIL_LENGTH;

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border px-3 sm:px-5">
            <SidebarTrigger aria-label={t('shell.toggleSidebar')} className="-ml-1" />

            <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-center" />

            {showsTrail ? (
                <AdminBreadcrumbs title={title} breadcrumbs={breadcrumbs} />
            ) : (
                <h1 className="min-w-0 truncate text-sm font-medium">{title}</h1>
            )}

            {actions ? (
                <div className="ml-auto flex shrink-0 items-center gap-2 pl-1">{actions}</div>
            ) : null}
        </header>
    );
}
