import { Wallet } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatFixedMoneyFromCents } from '@/lib/money';
import type { PaymentMethodCollection } from '../types';
import { BreakdownEmpty } from './BreakdownEmpty';
import { paymentMethodNameKey } from './payment-method-names';
import { ShareRow } from './ShareRow';
import { formatSharePercent } from './statistics-format';

type Props = {
    methods: PaymentMethodCollection[];
};

export function PaymentMethodBreakdownCard({ methods }: Props) {
    const { t, i18n } = useTranslation('admin');
    const locale = i18n.language;

    function methodName(code: string): string {
        const key = paymentMethodNameKey(code);

        return key === null ? code : t(key);
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('statistics.byPaymentMethod.title')}</CardTitle>
            </CardHeader>

            <CardContent>
                {methods.length === 0 ? (
                    <BreakdownEmpty icon={Wallet} message={t('statistics.byPaymentMethod.empty')} />
                ) : (
                    <ul className="grid gap-2">
                        {methods.map((method) => (
                            <ShareRow
                                key={method.code}
                                label={methodName(method.code)}
                                amount={formatFixedMoneyFromCents(method.collected_cents)}
                                caption={t('statistics.shareOfTotal', {
                                    share: formatSharePercent(method.share_percent, locale),
                                })}
                                sharePercent={method.share_percent}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
