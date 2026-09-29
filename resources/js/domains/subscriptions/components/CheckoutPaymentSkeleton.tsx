import { Skeleton } from '@/components/ui/skeleton';

export function CheckoutPaymentSkeleton() {
    return (
        <div aria-hidden="true" className="grid gap-4">
            <Skeleton className="h-12 w-full rounded-lg" />
            <Skeleton className="mx-auto h-4 w-32" />
            <Skeleton className="h-12 w-full rounded-lg" />
            <div className="grid grid-cols-2 gap-3">
                <Skeleton className="h-12 rounded-lg" />
                <Skeleton className="h-12 rounded-lg" />
            </div>
            <Skeleton className="h-12 w-full rounded-lg" />
        </div>
    );
}
