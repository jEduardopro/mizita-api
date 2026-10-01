import { Link, router, usePage } from '@inertiajs/react';
import { cn } from 'cn';
import { ChevronsUpDown, LoaderCircle, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
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
import { useMyBusinesses, useSelectCurrentBusiness } from '@/domains/businesses/queries';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom } from '@/lib/http';
import { initialsFrom } from '@/lib/initials';

const CALENDAR_URL = '/calendar';

const ONBOARDING_URL = '/onboarding';

const OWNER_ROLE = 'owner';

type CrestProps = {
    name: string;
    logoUrl: string | null;
    className?: string;
};

const CREST_FRAME_CLASSES = 'aspect-square size-8 shrink-0 rounded-md';

function BusinessCrest({ name, logoUrl, className }: CrestProps) {
    if (logoUrl !== null) {
        return (
            <img
                src={logoUrl}
                alt=""
                aria-hidden="true"
                className={cn(CREST_FRAME_CLASSES, 'object-contain', className)}
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className={cn(
                CREST_FRAME_CLASSES,
                'flex items-center justify-center bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground',
                className,
            )}
        >
            {initialsFrom(name)}
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

function currentOf<T extends { is_current: boolean }>(businesses: readonly T[]): T | undefined {
    return businesses.find((business) => business.is_current) ?? businesses[0];
}

function ownsAnyOf(businesses: readonly { role: string }[]): boolean {
    return businesses.some((business) => business.role === OWNER_ROLE);
}

function useBusinessSwitch() {
    const { t } = useTranslation('admin');
    const errorToast = useErrorToast();
    const selectCurrentBusiness = useSelectCurrentBusiness();
    const [isEntering, setIsEntering] = useState(false);

    async function switchTo(businessId: string) {
        errorToast.dismiss();

        try {
            await selectCurrentBusiness.mutateAsync(businessId);
        } catch (error) {
            errorToast.show(formMessageFrom(error, t('shell.switchFailed')));

            return;
        }

        setIsEntering(true);
        router.visit(CALENDAR_URL, { onFinish: () => setIsEntering(false) });
    }

    return {
        switchTo,
        isSwitching: selectCurrentBusiness.isPending || isEntering,
    };
}

export function BusinessSwitcher() {
    const { t } = useTranslation('admin');
    const { name } = usePage().props;
    const { isMobile } = useSidebar();
    const { data: businesses = [], isPending } = useMyBusinesses();
    const { switchTo, isSwitching } = useBusinessSwitch();

    const current = currentOf(businesses);
    const offersCreation = ! isPending && ! ownsAnyOf(businesses);

    function selectBusiness(businessId: string) {
        if (businessId === current?.id) {
            return;
        }

        void switchTo(businessId);
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            aria-label={t('shell.businessSwitcher')}
                            aria-busy={isSwitching}
                            disabled={isSwitching}
                            className="cursor-pointer data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
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

                            {isSwitching ? (
                                <LoaderCircle
                                    aria-hidden="true"
                                    className="ml-auto size-4 opacity-60 motion-safe:animate-spin"
                                />
                            ) : (
                                <ChevronsUpDown className="ml-auto size-4 opacity-60" />
                            )}
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                        className="min-w-60 rounded-lg"
                    >
                        {businesses.length > 0 ? (
                            <>
                                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                    {t('shell.businesses')}
                                </DropdownMenuLabel>

                                <DropdownMenuRadioGroup
                                    value={current?.id}
                                    onValueChange={selectBusiness}
                                >
                                    {businesses.map((business) => (
                                        <DropdownMenuRadioItem
                                            key={business.id}
                                            value={business.id}
                                            disabled={isSwitching}
                                            className="cursor-pointer gap-2 py-3 md:py-2"
                                        >
                                            <BusinessCrest
                                                name={business.name}
                                                logoUrl={business.logo_url}
                                                className="size-6 rounded-sm text-[0.625rem]"
                                            />
                                            <span className="truncate">{business.name}</span>
                                        </DropdownMenuRadioItem>
                                    ))}
                                </DropdownMenuRadioGroup>

                                {offersCreation ? <DropdownMenuSeparator /> : null}
                            </>
                        ) : null}

                        {offersCreation ? (
                            <DropdownMenuItem asChild className="cursor-pointer gap-2 py-3 md:py-2">
                                <Link href={ONBOARDING_URL}>
                                    <Plus className="size-4" />
                                    {t('shell.createBusiness')}
                                </Link>
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>

                <span role="status" className="sr-only">
                    {isSwitching ? t('shell.switchingBusiness') : null}
                </span>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
