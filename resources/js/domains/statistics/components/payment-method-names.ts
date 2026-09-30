export type PaymentMethodNameKey =
    | 'statistics.byPaymentMethod.methods.cash'
    | 'statistics.byPaymentMethod.methods.bank_transfer'
    | 'statistics.byPaymentMethod.methods.card';

const NAME_KEYS: Record<string, PaymentMethodNameKey> = {
    cash: 'statistics.byPaymentMethod.methods.cash',
    bank_transfer: 'statistics.byPaymentMethod.methods.bank_transfer',
    card: 'statistics.byPaymentMethod.methods.card',
};

export function paymentMethodNameKey(code: string): PaymentMethodNameKey | null {
    return NAME_KEYS[code] ?? null;
}
