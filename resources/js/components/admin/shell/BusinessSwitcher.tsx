import { Link, usePage } from '@inertiajs/react';
import { cn } from 'cn';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
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
import { Skeleton } from '@/components/ui/skeleton';
import { useMyBusinesses } from '@/domains/businesses/queries';
import { initialsFrom } from '@/lib/initials';

type CrestProps = {
    name: string;
    logoUrl: string | null;
    className?: string;
};

function BusinessCrest({ name, logoUrl, className }: CrestProps) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground',
                className,
            )}
        >
            {logoUrl === null ? (
                initialsFrom(name)
            ) : (
                <img src={logoUrl} alt="" className="size-full object-cover" />
            )}
        </span>
    );
}

type IdentityProps = {
    name: string;
    slug: string | undefined;
    logoUrl: string | null;
};

function BusinessIdentity({ name, slug, logoUrl }: IdentityProps) {
    return (
        <>
            <BusinessCrest name={name} logoUrl={logoUrl} />

            <span className="grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-medium">{name}</span>
                {slug === undefined ? null : (
                    <span className="truncate text-xs text-muted-foreground">/{slug}</span>
                )}
            </span>
        </>
    );
}

function BusinessIdentitySkeleton() {
    return (
        <>
            <Skeleton className="size-8 shrink-0 rounded-md" />

            <span className="grid flex-1 gap-1.5">
                <Skeleton className="h-3.5 w-24" />
                <Skeleton className="h-3 w-16" />
            </span>
        </>
    );
}

export function BusinessSwitcher() {
    const { t } = useTranslation('admin');
    const { name } = usePage().props;
    const { isMobile } = useSidebar();
    const { data: businesses, isPending } = useMyBusinesses();

    const current = businesses?.[0];

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            aria-label={t('shell.businessSwitcher')}
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {isPending ? (
                                <BusinessIdentitySkeleton />
                            ) : (
                                <BusinessIdentity
                                    name={current?.name ?? name}
                                    slug={current?.slug}
                                    logoUrl={current?.logo_url ?? null}
                                />
                            )}

                            <ChevronsUpDown className="ml-auto size-4 opacity-60" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                        className="min-w-60 rounded-lg"
                    >
                        {businesses && businesses.length > 0 ? (
                            <>
                                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                    {t('shell.currentBusiness')}
                                </DropdownMenuLabel>

                                {businesses.map((business) => (
                                    <DropdownMenuItem
                                        key={business.id}
                                        disabled
                                        className="gap-2 py-3 md:py-2"
                                    >
                                        <BusinessCrest
                                            name={business.name}
                                            logoUrl={business.logo_url}
                                            className="size-6 rounded-sm text-[0.625rem]"
                                        />
                                        <span className="truncate">{business.name}</span>
                                        {business.id === current?.id ? (
                                            <Check className="ml-auto size-4" />
                                        ) : null}
                                    </DropdownMenuItem>
                                ))}

                                <DropdownMenuSeparator />
                            </>
                        ) : null}

                        <DropdownMenuItem asChild className="gap-2 py-3 md:py-2">
                            <Link href="/onboarding">
                                <Plus className="size-4" />
                                {t('shell.createBusiness')}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
