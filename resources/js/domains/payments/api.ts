import { api } from '@/lib/api';
import type {
    AppointmentPayment,
    ChargePayload,
    PaymentMethod,
    RecordTransactionPayload,
} from './types';

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
