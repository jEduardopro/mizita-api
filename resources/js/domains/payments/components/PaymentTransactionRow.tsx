import { cn } from 'cn';
import { Ban } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { formatMoneyFromCents } from '@/lib/money';
import { formatTransactionDate } from './payment-dates';
import type { PaymentTransaction, PaymentTransactionType } from '../types';

const MINUS_SIGN = '−';

const TRANSACTION_TYPE_LABEL_KEYS = {
    approved: 'payments.transaction.types.approved',
    void: 'payments.transaction.types.void',
    refund: 'payments.transaction.types.refund',
    failed: 'payments.transaction.types.failed',
} as const satisfies Record<PaymentTransactionType, string>;

const TRANSACTION_TYPE_AMOUNT_PREFIXES = {
    approved: '',
    void: MINUS_SIGN,
    refund: MINUS_SIGN,
    failed: '',
} as const satisfies Record<PaymentTransactionType, string>;

const TRANSACTION_TYPE_AMOUNT_CLASSES = {
    approved: 'font-semibold',
    void: 'text-muted-foreground',
    refund: 'text-muted-foreground',
    failed: 'text-muted-foreground',
} as const satisfies Record<PaymentTransactionType, string>;

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
    paidCents: number;
    currencyCode: string;
    timezone: string;
    onVoid: () => void;
};

export function PaymentTransactionRow({
    transaction,
    paidCents,
    currencyCode,
    timezone,
    onVoid,
}: Props) {
    const { t, i18n } = useTranslation('admin');

    const money = (cents: number) => formatMoneyFromCents(cents, currencyCode, i18n.language);
    const type = transaction.type;
    const date = formatTransactionDate(transaction.processed_at, timezone, i18n.language);
    const carriesBreakdown = transaction.subtotal_pre_discount_cents > 0;
    const canVoid = type === 'approved' && paidCents > 0;
    const isFullyReversed = type === 'approved' && paidCents === 0;

    return (
        <AccordionItem value={transaction.id} className="border-b border-border last:border-b-0">
            <AccordionTrigger className="min-h-11 items-center gap-3 py-2.5 hover:no-underline md:min-h-9">
                <span className="min-w-0">
                    {t(TRANSACTION_TYPE_LABEL_KEYS[type])} ·{' '}
                    <span className="capitalize">{date}</span>
                </span>

                <span
                    className={cn(
                        'ml-auto shrink-0 tabular-nums',
                        TRANSACTION_TYPE_AMOUNT_CLASSES[type],
                    )}
                >
                    {TRANSACTION_TYPE_AMOUNT_PREFIXES[type]}
                    {money(transaction.total_cents)}
                </span>
            </AccordionTrigger>

            <AccordionContent className="grid h-auto gap-3 pb-4">
                {carriesBreakdown ? (
                    <TransactionDetail
                        label={t('payments.panel.subtotal')}
                        value={money(transaction.subtotal_pre_discount_cents)}
                    />
                ) : null}

                {carriesBreakdown && transaction.subtotal_discount_cents > 0 ? (
                    <TransactionDetail
                        label={t('payments.panel.discount')}
                        value={`${MINUS_SIGN}${money(transaction.subtotal_discount_cents)}`}
                    />
                ) : null}

                <TransactionDetail
                    label={t('payments.transaction.method')}
                    value={transaction.payment_method.label}
                />

                <TransactionDetail
                    label={t('payments.transaction.reference')}
                    value={transaction.id}
                    valueClassName="font-mono text-xs break-all"
                />

                {isFullyReversed ? (
                    <p className="text-pretty text-sm text-muted-foreground">
                        {t('payments.errors.voidNotPossible')}
                    </p>
                ) : null}

                {canVoid ? (
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
                ) : null}
            </AccordionContent>
        </AccordionItem>
    );
}
