import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import {
    chargeAppointment,
    getAppointmentPayment,
    listPaymentMethodCatalog,
    listPaymentMethods,
    listSales,
    listTransactions,
    recordPaymentTransaction,
    voidPaymentTransaction,
} from './api';
import type {
    ChargePayload,
    RecordTransactionPayload,
    SaleListParams,
    TransactionListParams,
} from './types';

const PAYMENT_METHODS_LIFETIME_MS = 5 * 60 * 1000;

export const paymentKeys = {
    all: ['payments'] as const,
    methods: () => [...paymentKeys.all, 'methods'] as const,
    methodCatalog: () => [...paymentKeys.all, 'method-catalog'] as const,
    forAppointment: (appointmentId: string) =>
        [...paymentKeys.all, 'appointment', appointmentId] as const,
    history: () => [...paymentKeys.all, 'history'] as const,
    sales: (params: SaleListParams) => [...paymentKeys.history(), 'sales', params] as const,
    transactions: (params: TransactionListParams) =>
        [...paymentKeys.history(), 'transactions', params] as const,
};

export function usePaymentMethods() {
    return useQuery({
        queryKey: paymentKeys.methods(),
        queryFn: ({ signal }) => listPaymentMethods(signal),
        staleTime: PAYMENT_METHODS_LIFETIME_MS,
    });
}

export function usePaymentMethodCatalog() {
    return useQuery({
        queryKey: paymentKeys.methodCatalog(),
        queryFn: ({ signal }) => listPaymentMethodCatalog(signal),
        staleTime: PAYMENT_METHODS_LIFETIME_MS,
    });
}

export function useSales(params: SaleListParams) {
    return useQuery({
        queryKey: paymentKeys.sales(params),
        queryFn: ({ signal }) => listSales(params, signal),
        placeholderData: keepPreviousData,
    });
}

export function useTransactions(params: TransactionListParams) {
    return useQuery({
        queryKey: paymentKeys.transactions(params),
        queryFn: ({ signal }) => listTransactions(params, signal),
        placeholderData: keepPreviousData,
    });
}

export function useAppointmentPayment(appointmentId: string, enabled = true) {
    return useQuery({
        queryKey: paymentKeys.forAppointment(appointmentId),
        queryFn: ({ signal }) => getAppointmentPayment(appointmentId, signal),
        enabled,
    });
}

function useAppointmentPaymentInvalidation(appointmentId: string) {
    const queryClient = useQueryClient();

    return useCallback(() => {
        void queryClient.invalidateQueries({
            queryKey: paymentKeys.forAppointment(appointmentId),
        });
        void queryClient.invalidateQueries({ queryKey: paymentKeys.history() });
    }, [queryClient, appointmentId]);
}

function useAppointmentPaymentMutation<TVariables, TData>(
    appointmentId: string,
    mutationFn: (variables: TVariables) => Promise<TData>,
) {
    const invalidatePayment = useAppointmentPaymentInvalidation(appointmentId);

    return useMutation({ mutationFn, onSuccess: invalidatePayment });
}

export function useChargeAppointment(appointmentId: string) {
    return useAppointmentPaymentMutation(appointmentId, (payload: ChargePayload) =>
        chargeAppointment(appointmentId, payload),
    );
}

export function useRecordPaymentTransaction(appointmentId: string) {
    return useAppointmentPaymentMutation(
        appointmentId,
        ({ paymentId, payload }: { paymentId: string; payload: RecordTransactionPayload }) =>
            recordPaymentTransaction(paymentId, payload),
    );
}

export function useVoidPaymentTransaction(appointmentId: string) {
    return useAppointmentPaymentMutation(
        appointmentId,
        ({ paymentId, transactionId }: { paymentId: string; transactionId: string }) =>
            voidPaymentTransaction(paymentId, transactionId),
    );
}
