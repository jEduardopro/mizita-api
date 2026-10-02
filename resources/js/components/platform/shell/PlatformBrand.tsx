import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { WordmarkMark } from '@/components/shared/Wordmark';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { PLATFORM_BUSINESSES_URL } from '@/lib/platform-urls';

export function PlatformBrand() {
    const { t } = useTranslation('platform');
    const { name } = usePage().props;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild size="lg" tooltip={t('marker')}>
                    <Link href={PLATFORM_BUSINESSES_URL}>
                        <span
                            aria-hidden="true"
                            className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-foreground text-background"
                        >
                            <ShieldCheck className="size-4" />
                        </span>

                        <span className="grid flex-1 justify-items-start gap-1 leading-none">
                            <WordmarkMark name={name} size="sm" />
                            <span className="text-[0.625rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                {t('marker')}
                            </span>
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
