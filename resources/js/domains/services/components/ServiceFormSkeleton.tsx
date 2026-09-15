import { Skeleton } from '@/components/ui/skeleton';

const FIELD_ROWS = [0, 1, 2];

export function ServiceFormSkeleton() {
    return (
        <div
            role="status"
            aria-busy="true"
            className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <div className="grid gap-5">
                <Skeleton className="h-40 rounded-xl" />

                {FIELD_ROWS.map((row) => (
                    <Skeleton key={row} className="h-11 rounded-lg" />
                ))}
            </div>

            <Skeleton className="h-64 rounded-xl" />
        </div>
    );
}
