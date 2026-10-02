import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PlatformHeader } from '@/components/platform/shell/PlatformHeader';
import { PlatformSidebar } from '@/components/platform/shell/PlatformSidebar';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
};

export function PlatformLayout({ title, description, children }: Props) {
    const { sidebarOpen } = usePage().props;

    useFlashToast();

    return (
        <TooltipProvider>
            <Head title={title} />

            <SidebarProvider defaultOpen={sidebarOpen}>
                <PlatformSidebar />

                <SidebarInset className="min-w-0">
                    <div className="sticky top-0 z-20 shrink-0 bg-background/90 backdrop-blur-sm">
                        <PlatformHeader title={title} />
                    </div>

                    <div className="flex-1 px-5 py-8 sm:px-8">
                        {description ? (
                            <p className="mb-6 max-w-2xl text-sm text-pretty text-muted-foreground">
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
