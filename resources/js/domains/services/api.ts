import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type { DuplicateServicePayload, Service, ServiceListParams, ServicePayload } from './types';

export async function listServices(
    params: ServiceListParams,
    signal?: AbortSignal,
): Promise<Paginated<Service>> {
    const { data } = await api.get<Paginated<Service>>('/services', { params, signal });

    return data;
}

export async function getService(id: string, signal?: AbortSignal): Promise<Service> {
    const { data } = await api.get<{ data: Service }>(`/services/${id}`, { signal });

    return data.data;
}

export async function createService(payload: ServicePayload): Promise<Service> {
    const { data } = await api.post<{ data: Service }>('/services', payload);

    return data.data;
}

export async function updateService(id: string, payload: ServicePayload): Promise<Service> {
    const { data } = await api.put<{ data: Service }>(`/services/${id}`, payload);

    return data.data;
}

export async function deleteService(id: string): Promise<void> {
    await api.delete(`/services/${id}`);
}

export async function duplicateService(
    id: string,
    payload: DuplicateServicePayload,
): Promise<Service> {
    const { data } = await api.post<{ data: Service }>(`/services/${id}/duplicate`, payload);

    return data.data;
}

export async function attachServiceImage(id: string, image: File): Promise<Service> {
    const body = new FormData();

    body.append('image', image);

    const { data } = await api.post<{ data: Service }>(`/services/${id}/image`, body);

    return data.data;
}

export async function removeServiceImage(id: string): Promise<Service> {
    const { data } = await api.delete<{ data: Service }>(`/services/${id}/image`);

    return data.data;
}
