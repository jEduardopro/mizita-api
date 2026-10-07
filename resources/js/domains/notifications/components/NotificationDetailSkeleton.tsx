import { Skeleton } from '@/components/ui/skeleton';

const PLACEHOLDER_FIELDS = [0, 1, 2, 3];

export function NotificationDetailSkeleton() {
    return (
        <div role="status" aria-busy="true" className="grid max-w-2xl gap-6">
            <div className="flex items-start gap-4">
                <Skeleton className="h-[4.25rem] w-16 shrink-0 rounded-lg" />

                <div className="grid flex-1 gap-2 pt-1">
                    <Skeleton className="h-6 w-3/4" />
                    <Skeleton className="h-4 w-1/2" />
                </div>
            </div>

            <div className="grid gap-4 rounded-xl border border-border p-4 sm:grid-cols-2 sm:p-5">
                {PLACEHOLDER_FIELDS.map((field) => (
                    <div key={field} className="grid gap-1.5">
                        <Skeleton className="h-3 w-16" />
                        <Skeleton className="h-4 w-32" />
                    </div>
                ))}
            </div>
        </div>
    );
}
