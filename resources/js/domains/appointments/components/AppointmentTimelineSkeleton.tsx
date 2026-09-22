import { Skeleton } from '@/components/ui/skeleton';

const PLACEHOLDER_ROWS = [0, 1, 2, 3, 4];

export function AppointmentTimelineSkeleton() {
    return (
        <div role="status" aria-busy="true" className="grid gap-5">
            {PLACEHOLDER_ROWS.map((row) => (
                <div key={row} className="grid grid-cols-[3.25rem_1fr] gap-x-3 sm:grid-cols-[3.75rem_1fr] sm:gap-x-4">
                    <Skeleton className="h-10 rounded-md" />

                    <Skeleton className="h-10 rounded-lg" />
                </div>
            ))}
        </div>
    );
}
