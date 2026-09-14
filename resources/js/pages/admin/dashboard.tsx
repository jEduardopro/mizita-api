import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrentUser } from '@/hooks/use-current-user';
import { AdminLayout } from '@/layouts/AdminLayout';

export default function Dashboard() {
    const { data: user, isPending, isError, refetch, isFetching } = useCurrentUser();
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <AdminLayout title={t('dashboard.title')} description={t('dashboard.description')}>
            <div className="grid gap-5 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.account.title')}</CardTitle>
                    </CardHeader>

                    <CardContent>
                        {isPending ? (
                            <div className="grid gap-2" aria-live="polite" aria-busy="true">
                                <span className="sr-only">
                                    {t('dashboard.account.loading')}
                                </span>
                                <span className="h-4 w-40 rounded-sm bg-muted motion-safe:animate-pulse" />
                                <span className="h-4 w-56 rounded-sm bg-muted motion-safe:animate-pulse" />
                            </div>
                        ) : null}

                        {isError ? (
                            <div className="grid gap-3 justify-items-start">
                                <p className="text-sm text-muted-foreground">
                                    {t('dashboard.account.error')}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void refetch()}
                                    disabled={isFetching}
                                >
                                    {isFetching
                                        ? tCommon('actions.retrying')
                                        : tCommon('actions.tryAgain')}
                                </Button>
                            </div>
                        ) : null}

                        {user ? (
                            <dl className="grid gap-3">
                                <div>
                                    <dt className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                                        {t('dashboard.account.name')}
                                    </dt>
                                    <dd className="mt-0.5 text-sm font-medium">{user.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                                        {t('dashboard.account.email')}
                                    </dt>
                                    <dd className="mt-0.5 text-sm font-medium">{user.email}</dd>
                                </div>
                            </dl>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.upNext.title')}</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            {t('dashboard.upNext.body')}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
