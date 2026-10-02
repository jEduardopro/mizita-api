import { useTranslation } from 'react-i18next';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';

type Props = {
    title: string;
};

export function PlatformHeader({ title }: Props) {
    const { t } = useTranslation('platform');

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border px-3 sm:px-5">
            <SidebarTrigger aria-label={t('shell.toggleSidebar')} className="-ml-1 size-11 md:size-7" />

            <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-center" />

            <h1 className="min-w-0 truncate text-sm font-medium">{title}</h1>
        </header>
    );
}
