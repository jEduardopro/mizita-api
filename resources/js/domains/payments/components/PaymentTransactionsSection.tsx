import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Accordion } from '@/components/ui/accordion';
import { formatMoneyFromCents } from '@/lib/money';
import { PaymentTransactionRow } from './PaymentTransactionRow';
import { VoidTransactionDialog } from './VoidTransactionDialog';
import type { PaymentTransaction } from '../types';

type Props = {
    appointmentId: string;
    paymentId: string;
    transactions: PaymentTransaction[];
    paidCents: number;
    currencyCode: string;
    timezone: string;
    onChanged: () => void;
};

export function PaymentTransactionsSection({
    appointmentId,
    paymentId,
    transactions,
    paidCents,
    currencyCode,
    timezone,
    onChanged,
}: Props) {
    const { t, i18n } = useTranslation('admin');
    const [transactionToVoid, setTransactionToVoid] = useState<string | null>(null);

    return (
        <section className="grid gap-3">
            <h3 className="text-sm font-medium text-muted-foreground">
                {t('payments.panel.transactions')}
            </h3>

            {transactions.length === 0 ? null : (
                <Accordion type="single" collapsible>
                    {transactions.map((transaction) => (
                        <PaymentTransactionRow
                            key={transaction.id}
                            transaction={transaction}
                            currencyCode={currencyCode}
                            timezone={timezone}
                            onVoid={() => setTransactionToVoid(transaction.id)}
                        />
                    ))}
                </Accordion>
            )}

            <div className="flex items-baseline justify-between gap-4 border-t border-border pt-3 text-sm font-semibold">
                <span>{t('payments.panel.totalPaid')}</span>

                <span className="shrink-0 tabular-nums">
                    {formatMoneyFromCents(paidCents, currencyCode, i18n.language)}
                </span>
            </div>

            {transactionToVoid === null ? null : (
                <VoidTransactionDialog
                    appointmentId={appointmentId}
                    paymentId={paymentId}
                    transactionId={transactionToVoid}
                    open
                    onOpenChange={(next) => {
                        if (! next) {
                            setTransactionToVoid(null);
                        }
                    }}
                    onChanged={onChanged}
                />
            )}
        </section>
    );
}
