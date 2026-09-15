import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AdminHeader } from '@/components/admin/shell/AdminHeader';
import { AppSidebar } from '@/components/admin/shell/AppSidebar';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Props = {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
};

export function AdminLayout({ title, description, actions, children }: Props) {
    const { sidebarOpen } = usePage().props;

    useFlashToast();

    return (
        <TooltipProvider>
            <Head title={title} />

            <SidebarProvider defaultOpen={sidebarOpen}>
                <AppSidebar />

                <SidebarInset className="min-w-0">
                    <AdminHeader title={title} actions={actions} />

                    <div className="flex-1 px-5 py-8 sm:px-8">
                        {description ? (
                            <p className="mb-8 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                                {description}
                            </p>
                        ) : null}

                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
