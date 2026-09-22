export type PaymentStatus = 'pending' | 'partially_paid' | 'paid';

export type PaymentTransactionStatus = 'completed' | 'voided';

export type DiscountType = 'none' | 'percentage' | 'fixed';

export type PaymentMethod = {
    id: string;
    code: string;
    label: string;
    enabled: boolean;
    position: number;
    requires_integration: boolean;
};

export type PaymentItem = {
    id: string;
    name: string;
    amount_cents: number;
    position: number;
};

export type PaymentDiscount = {
    type: DiscountType;
    value: number;
    amount_cents: number;
};

export type PaymentTransaction = {
    id: string;
    status: PaymentTransactionStatus;
    amount_cents: number;
    payment_method: { id: string; code: string; label: string };
    processed_at: string;
    voided_at: string | null;
};

export type AppointmentPayment = {
    id: string;
    appointment_id: string;
    currency_code: string;
    status: PaymentStatus;
    items: PaymentItem[];
    subtotal_cents: number;
    discount: PaymentDiscount;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    transactions: PaymentTransaction[];
    created_at: string;
};

export type ChargeAddOnPayload = { name: string; amount_cents: number };

export type ChargePayload = {
    add_ons: ChargeAddOnPayload[];
    discount: { type: DiscountType; value: number } | null;
    payment_method_id: string;
    amount_cents: number;
};

export type RecordTransactionPayload = {
    payment_method_id: string;
    amount_cents: number;
};
