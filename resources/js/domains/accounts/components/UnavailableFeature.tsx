import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    body: string;
    actionLabel: string;
};

export function UnavailableFeature({ title, body, actionLabel }: Props) {
    const { t } = useTranslation('admin');

    return (
        <section className="grid justify-items-start gap-2 py-5 first:pt-1">
            <div className="flex flex-wrap items-center gap-2">
                <h4 className="text-sm font-semibold">{title}</h4>

                <Badge variant="outline" className="font-normal text-muted-foreground">
                    {t('security.comingSoon')}
                </Badge>
            </div>

            <p className="text-sm text-muted-foreground">{body}</p>

            <Button type="button" variant="outline" disabled className="mt-1 h-11 rounded-full px-5 md:h-9">
                {actionLabel}
            </Button>
        </section>
    );
}
