import { Banknote, CreditCard, Landmark, Wallet, type LucideIcon } from 'lucide-react';

export type PaymentMethodLabelKey =
    | 'payments.method.names.cash'
    | 'payments.method.names.bank_transfer'
    | 'payments.method.names.card';

const ICONS: Record<string, LucideIcon> = {
    cash: Banknote,
    bank_transfer: Landmark,
    card: CreditCard,
};

const LABEL_KEYS: Record<string, PaymentMethodLabelKey> = {
    cash: 'payments.method.names.cash',
    bank_transfer: 'payments.method.names.bank_transfer',
    card: 'payments.method.names.card',
};

export function paymentMethodIcon(code: string): LucideIcon {
    return ICONS[code] ?? Wallet;
}

export function paymentMethodLabelKey(code: string): PaymentMethodLabelKey | null {
    return LABEL_KEYS[code] ?? null;
}
