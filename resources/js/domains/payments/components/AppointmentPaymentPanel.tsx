import { ReceiptText, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { initialsFrom } from '@/lib/initials';
import { PaymentPanelSkeleton } from './PaymentPanelSkeleton';
import { PaymentPurchasesSection } from './PaymentPurchasesSection';
import { PaymentStatusBadge } from './PaymentStatusBadge';
import { PaymentTransactionsSection } from './PaymentTransactionsSection';
import { useAppointmentPayment } from '../queries';

type Props = {
    appointmentId: string;
    customerName: string;
    currencyCode: string;
    timezone: string;
    onChanged: () => void;
};

export function AppointmentPaymentPanel({
    appointmentId,
    customerName,
    currencyCode,
    timezone,
    onChanged,
}: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const payment = useAppointmentPayment(appointmentId);

    if (payment.isPending) {
        return <PaymentPanelSkeleton />;
    }

    if (payment.isError) {
        return (
            <Alert className="grid gap-3 p-4">
                <TriangleAlert aria-hidden="true" />

                <AlertTitle>{t('payments.errors.load')}</AlertTitle>

                <div className="col-start-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => void payment.refetch()}
                        className="h-11 px-4 md:h-9"
                    >
                        {tCommon('actions.tryAgain')}
                    </Button>
                </div>
            </Alert>
        );
    }

    const record = payment.data;

    if (! record) {
        return (
            <div className="grid justify-items-center gap-2 rounded-xl border border-dashed border-border px-6 py-10 text-center">
                <ReceiptText aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="text-sm font-medium">{t('payments.panel.empty.title')}</p>

                <p className="text-pretty text-sm text-muted-foreground">
                    {t('payments.panel.empty.body')}
                </p>
            </div>
        );
    }

    const currency = record.currency_code === '' ? currencyCode : record.currency_code;

    return (
        <div className="grid min-h-0 gap-6 overflow-y-auto overscroll-contain">
            <div className="flex items-center gap-3">
                <Avatar aria-hidden="true" className="shrink-0">
                    <AvatarFallback>{initialsFrom(customerName)}</AvatarFallback>
                </Avatar>

                <p className="min-w-0 flex-1 truncate text-sm font-medium">{customerName}</p>

                <PaymentStatusBadge status={record.status} />
            </div>

            <PaymentPurchasesSection
                items={record.items}
                subtotalCents={record.subtotal_cents}
                discountCents={record.discount.amount_cents}
                totalCents={record.total_cents}
                currencyCode={currency}
            />

            <PaymentTransactionsSection
                appointmentId={appointmentId}
                paymentId={record.id}
                transactions={record.transactions}
                paidCents={record.paid_cents}
                currencyCode={currency}
                timezone={timezone}
                onChanged={onChanged}
            />
        </div>
    );
}
