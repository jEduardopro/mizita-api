import type { DataTableStatus } from './types';

export function dataTableStatus(isPending: boolean, isError: boolean): DataTableStatus {
    if (isPending) {
        return 'pending';
    }

    return isError ? 'error' : 'ready';
}
