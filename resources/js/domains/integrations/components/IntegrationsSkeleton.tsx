import { Skeleton } from '@/components/ui/skeleton';

export function IntegrationsSkeleton() {
    return (
        <div aria-busy="true" className="grid gap-8">
            <Skeleton className="h-11 w-full rounded-lg sm:max-w-sm md:h-9" />

            <div className="grid gap-4">
                <div className="grid gap-2">
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-4 w-72 max-w-full" />
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Skeleton className="h-40 rounded-xl" />
                </div>
            </div>
        </div>
    );
}
