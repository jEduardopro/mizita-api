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
import { useBusinessSettings } from '@/domains/businesses/queries';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Props = {
    title: string;
    description?: string;
    breadcrumbs?: Breadcrumb[];
    actions?: ReactNode;
    newAppointment?: boolean;
    fullBleed?: boolean;
    children: ReactNode;
};

export function AdminLayout({
    title,
    description,
    breadcrumbs,
    actions,
    newAppointment = true,
    fullBleed = false,
    children,
}: Props) {
    const { sidebarOpen } = usePage().props;
    const { data: businessSettings } = useBusinessSettings();

    useFlashToast();

    return (
        <TooltipProvider>
            <Head title={title} />

            <SidebarProvider defaultOpen={sidebarOpen} className={cn(fullBleed && 'h-svh')}>
                <AppSidebar />

                <SidebarInset className={cn('min-w-0', fullBleed && 'overflow-hidden')}>
                    <div className="sticky top-0 z-20 shrink-0 bg-background/90 backdrop-blur-sm">
                        <AdminHeader
                            title={title}
                            breadcrumbs={breadcrumbs}
                            actions={
                                newAppointment ? (
                                    <NewAppointmentLauncher timezone={businessSettings?.timezone} />
                                ) : undefined
                            }
                        />

                        <AdminSubheader description={description} actions={actions} />
                    </div>

                    <div
                        className={cn(
                            'flex-1',
                            fullBleed ? 'flex min-h-0 flex-col' : 'px-5 py-8 sm:px-8',
                        )}
                    >
                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
