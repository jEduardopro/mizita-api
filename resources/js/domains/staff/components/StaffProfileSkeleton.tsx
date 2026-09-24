import { Skeleton } from '@/components/ui/skeleton';

const DETAIL_ROWS = [0, 1, 2, 3, 4];

export function StaffProfileSkeleton() {
    return (
        <div role="status" aria-busy="true" className="grid gap-6">
            <div className="flex items-start gap-4">
                <Skeleton className="size-14 shrink-0 rounded-full sm:size-16" />

                <div className="grid flex-1 gap-2 pt-2">
                    <Skeleton className="h-6 w-48" />
                    <Skeleton className="h-4 w-64 max-w-full" />
                </div>
            </div>

            <Skeleton className="h-11 w-full max-w-md" />

            <div className="grid max-w-2xl gap-4">
                {DETAIL_ROWS.map((row) => (
                    <div key={row} className="flex items-center gap-3">
                        <Skeleton className="size-4 rounded-sm" />
                        <Skeleton className="h-4 w-56 max-w-full" />
                    </div>
                ))}
            </div>
        </div>
    );
}
