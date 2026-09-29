import { Skeleton } from '@/components/ui/skeleton';

export function SubscriptionPlanSkeleton() {
    return (
        <div aria-hidden="true" className="@container/plan">
            <div className="grid gap-8 @min-[62rem]/plan:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] @min-[62rem]/plan:gap-12">
                <div className="grid content-start gap-3">
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="h-9 w-full max-w-xs" />
                    <Skeleton className="h-4 w-full max-w-sm" />
                </div>

                <div className="grid max-w-4xl gap-4 @min-[36rem]/plan:grid-cols-2">
                    <Skeleton className="h-96 rounded-xl" />
                    <Skeleton className="h-96 rounded-xl" />
                </div>
            </div>
        </div>
    );
}
