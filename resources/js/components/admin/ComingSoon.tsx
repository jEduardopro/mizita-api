import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function ComingSoon() {
    const { t } = useTranslation('admin');

    return (
        <Card className="max-w-2xl">
            <CardHeader>
                <CardTitle>{t('comingSoon.title')}</CardTitle>
            </CardHeader>

            <CardContent>
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {t('comingSoon.body')}
                </p>
            </CardContent>
        </Card>
    );
}
