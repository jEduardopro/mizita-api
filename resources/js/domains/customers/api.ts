import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type { Customer, CustomerListParams, CustomerPayload } from './types';

export async function listCustomers(
    params: CustomerListParams,
    signal?: AbortSignal,
): Promise<Paginated<Customer>> {
    const { data } = await api.get<Paginated<Customer>>('/customers', { params, signal });

    return data;
}

export async function getCustomer(id: string, signal?: AbortSignal): Promise<Customer> {
    const { data } = await api.get<{ data: Customer }>(`/customers/${id}`, { signal });

    return data.data;
}

export async function createCustomer(payload: CustomerPayload): Promise<Customer> {
    const { data } = await api.post<{ data: Customer }>('/customers', payload);

    return data.data;
}

export async function updateCustomer(id: string, payload: CustomerPayload): Promise<Customer> {
    const { data } = await api.put<{ data: Customer }>(`/customers/${id}`, payload);

    return data.data;
}

export async function deleteCustomer(id: string): Promise<void> {
    await api.delete(`/customers/${id}`);
}

export async function attachCustomerPhoto(id: string, photo: File): Promise<Customer> {
    const body = new FormData();

    body.append('photo', photo);

    const { data } = await api.post<{ data: Customer }>(`/customers/${id}/photo`, body);

    return data.data;
}

export async function removeCustomerPhoto(id: string): Promise<Customer> {
    const { data } = await api.delete<{ data: Customer }>(`/customers/${id}/photo`);

    return data.data;
}
