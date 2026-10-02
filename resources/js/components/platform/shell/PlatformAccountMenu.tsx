import { usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { usePlatformLogOut } from '@/hooks/use-platform-log-out';
import { initialsFrom } from '@/lib/initials';

type IdentityProps = {
    name: string;
    email: string;
};

function AdminIdentity({ name, email }: IdentityProps) {
    return (
        <>
            <Avatar className="size-8 rounded-md">
                <AvatarFallback className="rounded-md text-xs font-semibold">
                    {initialsFrom(name)}
                </AvatarFallback>
            </Avatar>

            <span className="grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-medium">{name}</span>
                <span className="truncate text-xs text-muted-foreground">{email}</span>
            </span>
        </>
    );
}

export function PlatformAccountMenu() {
    const { t } = useTranslation('platform');
    const { isMobile, state } = useSidebar();
    const { platformAdmin } = usePage().props;
    const logOut = usePlatformLogOut();

    const isCollapsed = state === 'collapsed' && ! isMobile;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            aria-label={t('shell.accountMenu')}
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {platformAdmin ? (
                                <AdminIdentity name={platformAdmin.name} email={platformAdmin.email} />
                            ) : (
                                <span className="flex-1 truncate text-left text-sm">{t('shell.logOut')}</span>
                            )}

                            <ChevronsUpDown className="ml-auto size-4 opacity-60" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="end"
                        side={isCollapsed ? 'left' : 'bottom'}
                        sideOffset={4}
                        className="min-w-60 rounded-lg"
                    >
                        {platformAdmin ? (
                            <>
                                <DropdownMenuLabel className="flex items-center gap-2 py-2 font-normal">
                                    <AdminIdentity name={platformAdmin.name} email={platformAdmin.email} />
                                </DropdownMenuLabel>

                                <DropdownMenuSeparator />
                            </>
                        ) : null}

                        <DropdownMenuItem onSelect={logOut} className="gap-2 py-3 md:py-2">
                            <LogOut className="size-4" />
                            {t('shell.logOut')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
