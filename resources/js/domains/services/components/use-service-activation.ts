import type { Service } from '../types';
import { useActiveServiceAllowance } from './use-active-service-allowance';

export type ServiceActivation = { blocked: false } | { blocked: true; limit: number };

export function useServiceActivation(service: Service | null): ServiceActivation {
    const allowance = useActiveServiceAllowance();
    const alreadyActive = service?.active ?? false;

    if (allowance.status !== 'limited' || allowance.allowsAnother || alreadyActive) {
        return { blocked: false };
    }

    return { blocked: true, limit: allowance.limit };
}
