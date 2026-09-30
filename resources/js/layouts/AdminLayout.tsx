import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from 'cn';
import type { Breadcrumb } from '@/components/admin/shell/AdminBreadcrumbs';
import { AdminHeader } from '@/components/admin/shell/AdminHeader';
import { AdminSubheader } from '@/components/admin/shell/AdminSubheader';
import { AppSidebar } from '@/components/admin/shell/AppSidebar';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { NewAppointmentLauncher } from '@/domains/appointments/components/NewAppointmentLauncher';
import { DEFAULT_CURRENCY_CODE } from '@/domains/businesses/components/settings/location-options';
import { useCalendarSettings } from '@/domains/businesses/queries';
import { CreateCustomerDialog } from '@/domains/customers/components/CreateCustomerDialog';
import { BusinessCurrencyProvider } from '@/hooks/use-business-currency';
import { CustomerCreationProvider } from '@/hooks/use-customer-creation';
import { useFlashToast } from '@/hooks/use-flash-toast';

export type AdminLayoutFrame = 'page' | 'viewport' | 'bleed';

type FrameClasses = {
    provider?: string;
    inset?: string;
    content: string;
};

const FRAME_CLASSES: Record<AdminLayoutFrame, FrameClasses> = {
    page: {
        content: 'px-5 py-8 sm:px-8',
    },
    viewport: {
        provider: 'md:[@media(min-height:40rem)]:h-svh',
        inset: 'md:[@media(min-height:40rem)]:overflow-hidden',
        content:
            'px-5 py-8 sm:px-8 md:[@media(min-height:40rem)]:flex md:[@media(min-height:40rem)]:min-h-0 md:[@media(min-height:40rem)]:flex-col md:[@media(min-height:40rem)]:overflow-y-auto',
    },
    bleed: {
        provider: 'h-svh',
        inset: 'overflow-hidden',
        content: 'flex min-h-0 flex-col',
    },
};

type Props = {
    title: string;
    description?: string;
    breadcrumbs?: Breadcrumb[];
    actions?: ReactNode;
    newAppointment?: boolean;
    frame?: AdminLayoutFrame;
    children: ReactNode;
};

export function AdminLayout({
    title,
    description,
    breadcrumbs,
    actions,
    newAppointment = true,
    frame = 'page',
    children,
}: Props) {
    const frameClasses = FRAME_CLASSES[frame];
    const { sidebarOpen } = usePage().props;
    const { data: calendarSettings } = useCalendarSettings();
    const currencyCode = calendarSettings?.currency_code ?? DEFAULT_CURRENCY_CODE;

    useFlashToast();

    return (
        <BusinessCurrencyProvider currencyCode={currencyCode}>
            <TooltipProvider>
                <Head title={title} />

                <CustomerCreationProvider>
                    <SidebarProvider defaultOpen={sidebarOpen} className={frameClasses.provider}>
                        <AppSidebar />

                        <SidebarInset className={cn('min-w-0', frameClasses.inset)}>
                            <div className="sticky top-0 z-20 shrink-0 bg-background/90 backdrop-blur-sm">
                                <AdminHeader
                                    title={title}
                                    breadcrumbs={breadcrumbs}
                                    actions={
                                        newAppointment ? (
                                            <NewAppointmentLauncher
                                                timezone={calendarSettings?.timezone}
                                            />
                                        ) : undefined
                                    }
                                />

                                <AdminSubheader description={description} actions={actions} />
                            </div>

                            <div className={cn('flex-1', frameClasses.content)}>
                                {children}
                            </div>
                        </SidebarInset>
                    </SidebarProvider>

                    <CreateCustomerDialog />
                </CustomerCreationProvider>
            </TooltipProvider>
        </BusinessCurrencyProvider>
    );
}
