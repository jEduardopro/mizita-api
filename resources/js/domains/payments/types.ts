export type PaymentStatus = 'pending' | 'partially_paid' | 'paid';

export type PaymentTransactionType = 'approved' | 'void' | 'refund' | 'failed';

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
};

export type PaymentTransaction = {
    id: string;
    type: PaymentTransactionType;
    subtotal_pre_discount_cents: number;
    discount: PaymentDiscount;
    subtotal_discount_cents: number;
    subtotal_cents: number;
    total_cents: number;
    payment_method: { id: string; code: string; label: string };
    processed_at: string;
};

export type AppointmentPayment = {
    id: string;
    appointment_id: string;
    currency_code: string;
    status: PaymentStatus;
    items: PaymentItem[];
    subtotal_cents: number;
    discount_amount_cents: number;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    transactions: PaymentTransaction[];
    created_at: string;
};

export type ChargeAddOnPayload = { name: string; amount_cents: number };

export type ChargePayload = {
    add_ons: ChargeAddOnPayload[];
    discount: PaymentDiscount | null;
    payment_method_id: string;
    amount_cents: number;
};

export type RecordTransactionPayload = {
    payment_method_id: string;
    amount_cents: number;
};
