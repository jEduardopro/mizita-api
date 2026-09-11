import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrentUser } from '@/hooks/use-current-user';
import { AdminLayout } from '@/layouts/AdminLayout';

/**
 * Rendered by `Inertia::render('admin/dashboard')`.
 *
 * No data arrives as an Inertia prop: the page mounts and the hook fetches the
 * account from `/api/user`, the same endpoint a native client will call.
 */
export default function Dashboard() {
    const { data: user, isPending, isError, refetch, isFetching } = useCurrentUser();

    return (
        <AdminLayout title="Dashboard" description="Your account, and what lands here next.">
            <div className="grid gap-5 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Signed in as</CardTitle>
                    </CardHeader>

                    <CardContent>
                        {isPending ? (
                            <div className="grid gap-2" aria-live="polite" aria-busy="true">
                                <span className="sr-only">Loading your account</span>
                                <span className="h-4 w-40 rounded-sm bg-muted motion-safe:animate-pulse" />
                                <span className="h-4 w-56 rounded-sm bg-muted motion-safe:animate-pulse" />
                            </div>
                        ) : null}

                        {isError ? (
                            <div className="grid gap-3 justify-items-start">
                                <p className="text-sm text-muted-foreground">
                                    We could not load your account just now.
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void refetch()}
                                    disabled={isFetching}
                                >
                                    {isFetching ? 'Retrying…' : 'Try again'}
                                </Button>
                            </div>
                        ) : null}

                        {user ? (
                            <dl className="grid gap-3">
                                <div>
                                    <dt className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                                        Name
                                    </dt>
                                    <dd className="mt-0.5 text-sm font-medium">{user.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                                        Email
                                    </dt>
                                    <dd className="mt-0.5 text-sm font-medium">{user.email}</dd>
                                </div>
                            </dl>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Nothing scheduled yet</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            Your agenda shows up here once a business exists, with its staff,
                            services and opening hours. Those screens are still being built.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
