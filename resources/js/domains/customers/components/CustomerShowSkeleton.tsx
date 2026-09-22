import { Skeleton } from '@/components/ui/skeleton';

const TABS = [0, 1, 2];

const DETAIL_ROWS = [0, 1, 2, 3, 4];

export function CustomerShowSkeleton() {
    return (
        <div role="status" aria-busy="true" className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6">
                <div className="flex items-center gap-3">
                    <Skeleton className="size-10 rounded-full" />

                    <Skeleton className="h-7 w-48 max-w-full rounded-lg" />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Skeleton className="h-11 w-full rounded-lg sm:w-44 md:h-9" />

                    <Skeleton className="size-11 rounded-lg md:size-9" />

                    <Skeleton className="size-11 rounded-lg md:size-9" />
                </div>
            </div>

            <div className="grid grid-cols-3 gap-1 border-b border-border pb-[5px]">
                {TABS.map((tab) => (
                    <Skeleton key={tab} className="h-11 rounded-lg" />
                ))}
            </div>

            <div className="grid gap-5 rounded-2xl border border-border bg-card p-5 sm:p-6">
                {DETAIL_ROWS.map((row) => (
                    <div
                        key={row}
                        className="grid gap-2 sm:grid-cols-[minmax(0,9rem)_minmax(0,1fr)] sm:gap-6"
                    >
                        <Skeleton className="h-4 w-24 rounded-md" />

                        <Skeleton className="h-4 w-full max-w-64 rounded-md" />
                    </div>
                ))}
            </div>
        </div>
    );
}
