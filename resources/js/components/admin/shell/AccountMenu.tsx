import { Link } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Palette, UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Skeleton } from '@/components/ui/skeleton';
import { useMyProfile } from '@/domains/staff/queries';
import { useCurrentUser, type CurrentUser } from '@/hooks/use-current-user';
import { useLogOut } from '@/hooks/use-log-out';
import { initialsFrom } from '@/lib/initials';
import { AppearanceMenu } from './AppearanceMenu';

type MenuSide = 'bottom' | 'left';

function menuSide(isMobile: boolean, isCollapsed: boolean): MenuSide {
    if (isMobile) {
        return 'bottom';
    }

    return isCollapsed ? 'left' : 'bottom';
}

type IdentityProps = {
    user: CurrentUser;
    photoUrl: string | null;
};

function AccountIdentity({ user, photoUrl }: IdentityProps) {
    return (
        <>
            <Avatar className="size-8 rounded-md">
                {photoUrl ? <AvatarImage src={photoUrl} alt="" className="rounded-md" /> : null}
                <AvatarFallback className="rounded-md text-xs font-semibold">
                    {initialsFrom(user.name)}
                </AvatarFallback>
            </Avatar>

            <span className="grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-medium">{user.name}</span>
                <span className="truncate text-xs text-muted-foreground">{user.email}</span>
            </span>
        </>
    );
}

function AccountIdentitySkeleton() {
    return (
        <>
            <Skeleton className="size-8 shrink-0 rounded-md" />

            <span className="grid flex-1 gap-1.5">
                <Skeleton className="h-3.5 w-24" />
                <Skeleton className="h-3 w-32" />
            </span>
        </>
    );
}

export function AccountMenu() {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { isMobile, state } = useSidebar();
    const { data: user } = useCurrentUser();
    const { data: profile } = useMyProfile();
    const photoUrl = profile?.photo_url ?? null;
    const logOut = useLogOut();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            aria-label={t('account.menu')}
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {user ? <AccountIdentity user={user} photoUrl={photoUrl} /> : <AccountIdentitySkeleton />}

                            <ChevronsUpDown className="ml-auto size-4 opacity-60" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="end"
                        side={menuSide(isMobile, state === 'collapsed')}
                        sideOffset={4}
                        className="min-w-60 rounded-lg"
                    >
                        {user ? (
                            <>
                                <DropdownMenuLabel className="flex items-center gap-2 py-2 font-normal">
                                    <AccountIdentity user={user} photoUrl={photoUrl} />
                                </DropdownMenuLabel>

                                <DropdownMenuSeparator />
                            </>
                        ) : null}

                        <DropdownMenuItem asChild className="gap-2 py-3 md:py-2">
                            <Link href="/settings/profile">
                                <UserRound className="size-4" />
                                {t('account.myAccount')}
                            </Link>
                        </DropdownMenuItem>

                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger className="gap-2 py-3 md:py-2">
                                <Palette className="size-4" />
                                {t('account.appearance')}
                            </DropdownMenuSubTrigger>

                            <DropdownMenuPortal>
                                <DropdownMenuSubContent className="min-w-40">
                                    <AppearanceMenu />
                                </DropdownMenuSubContent>
                            </DropdownMenuPortal>
                        </DropdownMenuSub>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem onSelect={logOut} className="gap-2 py-3 md:py-2">
                            <LogOut className="size-4" />
                            {tCommon('nav.logOut')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
