import type { DataTableStatus, DataTableToolbar } from './types';

type Props = {
    toolbar: DataTableToolbar | undefined;
    status: DataTableStatus;
    rowCount: number;
    showsPreviousRows: boolean;
};

function hasNothingToFilter(
    toolbar: DataTableToolbar,
    status: DataTableStatus,
    rowCount: number,
    showsPreviousRows: boolean,
): boolean {
    if (toolbar.hasActiveFilters) {
        return false;
    }

    if (status === 'pending') {
        return true;
    }

    return status === 'ready' && rowCount === 0 && ! showsPreviousRows;
}

export function DataTableToolbarSlot({ toolbar, status, rowCount, showsPreviousRows }: Props) {
    if (toolbar === undefined || hasNothingToFilter(toolbar, status, rowCount, showsPreviousRows)) {
        return null;
    }

    return <>{toolbar.content}</>;
}
