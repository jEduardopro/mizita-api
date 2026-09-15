import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';

type Props = {
    title: string;
    actions?: ReactNode;
};

export function AdminHeader({ title, actions }: Props) {
    const { t } = useTranslation('admin');

    return (
        <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background/90 px-3 backdrop-blur-sm sm:px-5">
            <SidebarTrigger aria-label={t('shell.toggleSidebar')} className="-ml-1" />

            <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-center" />

            <h1 className="truncate text-sm font-medium">{title}</h1>

            {actions ? <div className="ml-auto flex items-center gap-2">{actions}</div> : null}
        </header>
    );
}
