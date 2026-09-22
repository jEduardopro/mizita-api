import { Skeleton } from '@/components/ui/skeleton';

const PURCHASE_ROWS = [0, 1, 2];

const TRANSACTION_ROWS = [0, 1];

export function PaymentPanelSkeleton() {
    return (
        <div role="status" aria-busy="true" className="grid gap-6">
            <div className="flex items-center gap-3">
                <Skeleton className="size-8 rounded-full" />

                <Skeleton className="h-4 w-32 max-w-[40%]" />

                <Skeleton className="ml-auto h-5 w-20 rounded-4xl" />
            </div>

            <div className="grid gap-3">
                <Skeleton className="h-4 w-24" />

                {PURCHASE_ROWS.map((row) => (
                    <div key={row} className="flex items-center justify-between gap-4">
                        <Skeleton className="h-4 w-40 max-w-[55%]" />

                        <Skeleton className="h-4 w-16" />
                    </div>
                ))}
            </div>

            <div className="grid gap-3">
                <Skeleton className="h-4 w-28" />

                {TRANSACTION_ROWS.map((row) => (
                    <div key={row} className="flex items-center justify-between gap-4">
                        <Skeleton className="h-4 w-44 max-w-[60%]" />

                        <Skeleton className="h-4 w-16" />
                    </div>
                ))}
            </div>
        </div>
    );
}
