import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type {
    AppointmentPayment,
    ChargePayload,
    PaymentMethod,
    PaymentMethodCatalogEntry,
    PaymentTransactionReport,
    RecordTransactionPayload,
    Sale,
    SaleListParams,
    TransactionListParams,
} from './types';

export async function listSales(params: SaleListParams, signal?: AbortSignal): Promise<Paginated<Sale>> {
    const { data } = await api.get<Paginated<Sale>>('/payments/sales', { params, signal });

    return data;
}

export async function listTransactions(
    params: TransactionListParams,
    signal?: AbortSignal,
): Promise<Paginated<PaymentTransactionReport>> {
    const { data } = await api.get<Paginated<PaymentTransactionReport>>('/payments/transactions', {
        params,
        signal,
    });

    return data;
}

export async function listPaymentMethodCatalog(signal?: AbortSignal): Promise<PaymentMethodCatalogEntry[]> {
    const { data } = await api.get<{ data: PaymentMethodCatalogEntry[] }>('/payment-method-catalog', { signal });

    return data.data;
}

export async function listPaymentMethods(signal?: AbortSignal): Promise<PaymentMethod[]> {
    const { data } = await api.get<{ data: PaymentMethod[] }>('/payment-methods', { signal });

    return data.data;
}

export async function getAppointmentPayment(
    appointmentId: string,
    signal?: AbortSignal,
): Promise<AppointmentPayment | null> {
    const { data } = await api.get<{ data: AppointmentPayment | null }>(
        `/appointments/${appointmentId}/payment`,
        { signal },
    );

    return data.data ?? null;
}

export async function chargeAppointment(
    appointmentId: string,
    payload: ChargePayload,
): Promise<AppointmentPayment> {
    const { data } = await api.post<{ data: AppointmentPayment }>(
        `/appointments/${appointmentId}/payment`,
        payload,
    );

    return data.data;
}

export async function recordPaymentTransaction(
    paymentId: string,
    payload: RecordTransactionPayload,
): Promise<AppointmentPayment> {
    const { data } = await api.post<{ data: AppointmentPayment }>(
        `/payments/${paymentId}/transactions`,
        payload,
    );

    return data.data;
}

export async function voidPaymentTransaction(
    paymentId: string,
    transactionId: string,
): Promise<AppointmentPayment> {
    const { data } = await api.post<{ data: AppointmentPayment }>(
        `/payments/${paymentId}/transactions/${transactionId}/void`,
    );

    return data.data;
}
