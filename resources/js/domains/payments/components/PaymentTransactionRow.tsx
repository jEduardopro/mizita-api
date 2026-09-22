import { cn } from 'cn';
import { Ban } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { formatMoneyFromCents } from '@/lib/money';
import { formatTransactionDate } from './payment-dates';
import type { PaymentTransaction } from '../types';

type DetailProps = {
    label: string;
    value: string;
    valueClassName?: string;
};

function TransactionDetail({ label, value, valueClassName }: DetailProps) {
    return (
        <div className="grid gap-0.5 text-sm">
            <span className="text-muted-foreground">{label}</span>

            <span className={cn('font-medium', valueClassName)}>{value}</span>
        </div>
    );
}

type Props = {
    transaction: PaymentTransaction;
    currencyCode: string;
    timezone: string;
    onVoid: () => void;
};

export function PaymentTransactionRow({ transaction, currencyCode, timezone, onVoid }: Props) {
    const { t, i18n } = useTranslation('admin');

    const voided = transaction.status === 'voided';
    const amount = formatMoneyFromCents(transaction.amount_cents, currencyCode, i18n.language);
    const date = formatTransactionDate(transaction.processed_at, timezone, i18n.language);
    const statusLabel = voided ? t('payments.transaction.voided') : t('payments.transaction.completed');

    return (
        <AccordionItem value={transaction.id} className="border-b border-border last:border-b-0">
            <AccordionTrigger className="min-h-11 items-center gap-3 py-2.5 hover:no-underline md:min-h-9">
                <span className={cn('min-w-0 capitalize', voided && 'text-muted-foreground')}>
                    {statusLabel} · {date}
                </span>

                <span
                    className={cn(
                        'ml-auto shrink-0 tabular-nums',
                        voided ? 'text-muted-foreground line-through' : 'font-semibold',
                    )}
                >
                    {amount}
                </span>
            </AccordionTrigger>

            <AccordionContent className="grid h-auto gap-3 pb-4">
                <TransactionDetail
                    label={t('payments.transaction.method')}
                    value={transaction.payment_method.label}
                />

                <TransactionDetail
                    label={t('payments.transaction.reference')}
                    value={transaction.id}
                    valueClassName="font-mono text-xs break-all"
                />

                {voided ? null : (
                    <div className="flex">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onVoid}
                            className="h-11 px-2.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive md:h-8"
                        >
                            <Ban aria-hidden="true" />
                            {t('payments.transaction.void')}
                        </Button>
                    </div>
                )}
            </AccordionContent>
        </AccordionItem>
    );
}
