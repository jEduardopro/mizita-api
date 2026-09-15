import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Breadcrumb } from '@/components/admin/shell/AdminBreadcrumbs';
import { AdminHeader } from '@/components/admin/shell/AdminHeader';
import { AdminSubheader } from '@/components/admin/shell/AdminSubheader';
import { AppSidebar } from '@/components/admin/shell/AppSidebar';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Props = {
    title: string;
    description?: string;
    breadcrumbs?: Breadcrumb[];
    actions?: ReactNode;
    children: ReactNode;
};

export function AdminLayout({ title, description, breadcrumbs, actions, children }: Props) {
    const { sidebarOpen } = usePage().props;

    useFlashToast();

    return (
        <TooltipProvider>
            <Head title={title} />

            <SidebarProvider defaultOpen={sidebarOpen}>
                <AppSidebar />

                <SidebarInset className="min-w-0">
                    <div className="sticky top-0 z-20 shrink-0 bg-background/90 backdrop-blur-sm">
                        <AdminHeader title={title} breadcrumbs={breadcrumbs} />

                        <AdminSubheader description={description} actions={actions} />
                    </div>

                    <div className="flex-1 px-5 py-8 sm:px-8">{children}</div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
