import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
    type OnChangeFn,
    type PaginationState,
    type SortingState,
} from '@tanstack/react-table';
import { cn } from 'cn';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DataTableError } from './DataTableError';
import { DataTablePagination } from './DataTablePagination';
import { DataTableSkeleton } from './DataTableSkeleton';
import type { DataTableStatus } from './types';

type SortState = false | 'asc' | 'desc';

const ariaSort = {
    asc: 'ascending',
    desc: 'descending',
} as const;

function ariaSortOf(canSort: boolean, state: SortState): 'ascending' | 'descending' | 'none' | undefined {
    if (! canSort) {
        return undefined;
    }

    return state === false ? 'none' : ariaSort[state];
}

function SortIcon({ state }: { state: SortState }) {
    if (state === 'asc') {
        return <ArrowUp aria-hidden="true" className="size-3.5" />;
    }

    if (state === 'desc') {
        return <ArrowDown aria-hidden="true" className="size-3.5" />;
    }

    return <ChevronsUpDown aria-hidden="true" className="size-3.5 text-muted-foreground" />;
}

type Props<TData, TValue> = {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    getRowId: (row: TData) => string;
    caption: string;
    pagination: PaginationState;
    onPaginationChange: OnChangeFn<PaginationState>;
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;
    pageCount: number;
    totalRows: number;
    status: DataTableStatus;
    isFetching: boolean;
    onRetry: () => void;
    emptyState: ReactNode;
    renderCard: (row: TData) => ReactNode;
    toolbar?: ReactNode;
};

export function DataTable<TData, TValue>({
    columns,
    data,
    getRowId,
    caption,
    pagination,
    onPaginationChange,
    sorting,
    onSortingChange,
    pageCount,
    totalRows,
    status,
    isFetching,
    onRetry,
    emptyState,
    renderCard,
    toolbar,
}: Props<TData, TValue>) {
    const { t } = useTranslation('common');

    const table = useReactTable({
        data,
        columns,
        state: { pagination, sorting },
        onPaginationChange,
        onSortingChange,
        getCoreRowModel: getCoreRowModel(),
        getRowId: (row) => getRowId(row),
        manualPagination: true,
        manualSorting: true,
        enableMultiSort: false,
        pageCount,
    });

    return (
        <div className="grid gap-4">
            {toolbar}

            {status === 'pending' ? <DataTableSkeleton columns={columns.length} /> : null}

            {status === 'error' ? <DataTableError onRetry={onRetry} /> : null}

            {status === 'ready' && data.length === 0 ? emptyState : null}

            {status === 'ready' && data.length > 0 ? (
                <div
                    aria-busy={isFetching}
                    className={cn(
                        'motion-safe:transition-opacity',
                        isFetching ? 'opacity-60' : undefined,
                    )}
                >
                    <div className="grid gap-3 md:hidden">
                        {table.getRowModel().rows.map((row) => (
                            <Fragment key={row.id}>{renderCard(row.original)}</Fragment>
                        ))}
                    </div>

                    <div className="hidden overflow-hidden rounded-xl border border-border bg-card md:block">
                        <Table>
                            <TableCaption className="sr-only">{caption}</TableCaption>

                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id} className="hover:bg-transparent">
                                        {headerGroup.headers.map((header) => {
                                            const sortState = header.column.getIsSorted();
                                            const canSort = header.column.getCanSort();
                                            const label = flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            );

                                            return (
                                                <TableHead
                                                    key={header.id}
                                                    aria-sort={ariaSortOf(canSort, sortState)}
                                                    className="h-11 px-3 text-xs font-medium text-muted-foreground"
                                                >
                                                    {canSort ? (
                                                        <button
                                                            type="button"
                                                            onClick={header.column.getToggleSortingHandler()}
                                                            className="-mx-1.5 inline-flex h-9 items-center gap-1.5 rounded-md px-1.5 outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                                                        >
                                                            {label}
                                                            <SortIcon state={sortState} />
                                                        </button>
                                                    ) : (
                                                        label
                                                    )}
                                                </TableHead>
                                            );
                                        })}
                                    </TableRow>
                                ))}
                            </TableHeader>

                            <TableBody>
                                {table.getRowModel().rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id} className="px-3 py-2.5">
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            ) : null}

            {status === 'ready' && totalRows > 0 ? (
                <DataTablePagination
                    pagination={pagination}
                    onPaginationChange={onPaginationChange}
                    pageCount={Math.max(pageCount, 1)}
                    totalRows={totalRows}
                />
            ) : null}

            <span role="status" className="sr-only">
                {isFetching ? t('table.loading') : ''}
            </span>
        </div>
    );
}
