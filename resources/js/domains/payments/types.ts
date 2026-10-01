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

export const SALE_SORT_FIELDS = ['created_at', 'total'] as const;

export type SaleSortField = (typeof SALE_SORT_FIELDS)[number];

export const TRANSACTION_SORT_FIELDS = ['processed_at', 'amount'] as const;

export type TransactionSortField = (typeof TRANSACTION_SORT_FIELDS)[number];

export const SALE_REFERENCE_MAX_LENGTH = 8;

export const PAYMENT_REPORT_CUSTOMER_FILTER_MAX_SIZE = 100;

export type PaymentReportCustomer = {
    id: string;
    name: string;
};

export type Sale = {
    id: string;
    created_at: string;
    customer: PaymentReportCustomer;
    status: PaymentStatus;
    total_cents: number;
    currency_code: string;
    reference_code: string;
};

export type PaymentTransactionReport = {
    id: string;
    processed_at: string;
    customer: PaymentReportCustomer;
    amount_cents: number;
    currency_code: string;
    type: PaymentTransactionType;
    method: { code: string; name: string };
};

export type PaymentMethodCatalogEntry = {
    id: string;
    code: string;
    name: string;
};

export type PaymentReportCriteria = {
    from?: string;
    to?: string;
    customer_ids?: string[];
    page: number;
    per_page: number;
    direction: 'asc' | 'desc';
};

export type SaleListParams = PaymentReportCriteria & {
    reference?: string;
    statuses?: PaymentStatus[];
    sort: SaleSortField;
};

export type TransactionListParams = PaymentReportCriteria & {
    types?: PaymentTransactionType[];
    methods?: string[];
    sort: TransactionSortField;
};
