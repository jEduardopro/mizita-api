import { Skeleton } from '@/components/ui/skeleton';

const SECTIONS = [0, 1];

const FIELD_ROWS = [0, 1, 2, 3];

export function CustomerFormSkeleton() {
    return (
        <div role="status" aria-busy="true" className="grid gap-6 lg:grid-cols-2 lg:items-start">
            {SECTIONS.map((section) => (
                <div key={section} className="grid gap-5 rounded-2xl border border-border p-5 sm:p-6">
                    <Skeleton className="h-6 w-40 rounded-lg" />

                    {FIELD_ROWS.map((row) => (
                        <Skeleton key={row} className="h-11 rounded-lg" />
                    ))}
                </div>
            ))}
        </div>
    );
}
