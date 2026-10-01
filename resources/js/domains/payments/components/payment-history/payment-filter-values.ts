import type { PaymentStatus, PaymentTransactionType } from '../../types';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const METHOD_CODE_PATTERN = /^[a-z][a-z_]*$/;

export const SALE_STATUS_FILTERS = ['paid', 'partially_paid', 'pending'] as const satisfies readonly PaymentStatus[];

export const TRANSACTION_TYPE_FILTERS = ['approved', 'void'] as const satisfies readonly PaymentTransactionType[];

export type TransactionTypeFilter = (typeof TRANSACTION_TYPE_FILTERS)[number];

export const PAYMENT_STATUS_LABEL_KEYS = {
    pending: 'payments.status.pending',
    partially_paid: 'payments.status.partiallyPaid',
    paid: 'payments.status.paid',
} as const satisfies Record<PaymentStatus, string>;

export const TRANSACTION_TYPE_LABEL_KEYS = {
    approved: 'payments.transaction.types.approved',
    void: 'payments.transaction.types.void',
    refund: 'payments.transaction.types.refund',
    failed: 'payments.transaction.types.failed',
} as const satisfies Record<PaymentTransactionType, string>;

export const METHOD_FILTER_MAXIMUM_SIZE = 20;

export function isUuid(candidate: string): candidate is string {
    return UUID_PATTERN.test(candidate);
}

export function isMethodCode(candidate: string): candidate is string {
    return METHOD_CODE_PATTERN.test(candidate);
}

export function isSaleStatusFilter(candidate: string): candidate is PaymentStatus {
    return (SALE_STATUS_FILTERS as readonly string[]).includes(candidate);
}

export function isTransactionTypeFilter(candidate: string): candidate is TransactionTypeFilter {
    return (TRANSACTION_TYPE_FILTERS as readonly string[]).includes(candidate);
}

export function listOrUndefined<TValue>(values: TValue[]): TValue[] | undefined {
    return values.length === 0 ? undefined : values;
}
