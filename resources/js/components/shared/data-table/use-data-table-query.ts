import type { OnChangeFn, PaginationState, SortingState } from '@tanstack/react-table';
import { useCallback, useMemo } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import {
    DEFAULT_PAGE_SIZE,
    PAGE_SIZES,
    type DataTableSort,
    type PageSize,
    type SortDirection,
} from './types';

const FIRST_PAGE = 1;

function readPage(raw: string | null): number {
    const page = Number.parseInt(raw ?? '', 10);

    return Number.isInteger(page) && page >= FIRST_PAGE ? page : FIRST_PAGE;
}

function readPageSize(raw: string | null): PageSize {
    const size = Number.parseInt(raw ?? '', 10);

    return PAGE_SIZES.find((candidate) => candidate === size) ?? DEFAULT_PAGE_SIZE;
}

function readDirection(raw: string | null, fallback: SortDirection): SortDirection {
    return raw === 'asc' || raw === 'desc' ? raw : fallback;
}

type Params<TField extends string> = {
    sortableFields: readonly TField[];
    defaultSort: DataTableSort<TField>;
};

export type DataTableQuery<TField extends string> = {
    page: number;
    perPage: PageSize;
    sort: DataTableSort<TField>;
    search: string;
    pagination: PaginationState;
    sorting: SortingState;
    onPaginationChange: OnChangeFn<PaginationState>;
    onSortingChange: OnChangeFn<SortingState>;
    setSearch: (value: string) => void;
};

export function useDataTableQuery<TField extends string>({
    sortableFields,
    defaultSort,
}: Params<TField>): DataTableQuery<TField> {
    const url = useUrlQueryState();

    const page = readPage(url.read('page'));
    const perPage = readPageSize(url.read('per_page'));

    const requestedField = url.read('sort');
    const field = sortableFields.find((candidate) => candidate === requestedField) ?? defaultSort.field;
    const direction = readDirection(url.read('direction'), defaultSort.direction);

    const search = url.read('search') ?? '';

    const pagination = useMemo(
        () => ({ pageIndex: page - FIRST_PAGE, pageSize: perPage }),
        [page, perPage],
    );

    const sorting = useMemo(
        () => [{ id: field, desc: direction === 'desc' }],
        [field, direction],
    );

    const { write } = url;

    const onPaginationChange = useCallback<OnChangeFn<PaginationState>>(
        (updater) => {
            const next = typeof updater === 'function' ? updater(pagination) : updater;
            const resized = next.pageSize !== pagination.pageSize;
            const nextPage = resized ? FIRST_PAGE : next.pageIndex + FIRST_PAGE;

            write({
                page: nextPage === FIRST_PAGE ? null : String(nextPage),
                per_page: next.pageSize === DEFAULT_PAGE_SIZE ? null : String(next.pageSize),
            });
        },
        [pagination, write],
    );

    const onSortingChange = useCallback<OnChangeFn<SortingState>>(
        (updater) => {
            const next = typeof updater === 'function' ? updater(sorting) : updater;
            const requested = next[0];

            const chosen: DataTableSort<TField> =
                requested === undefined
                    ? defaultSort
                    : {
                          field:
                              sortableFields.find((candidate) => candidate === requested.id) ??
                              defaultSort.field,
                          direction: requested.desc ? 'desc' : 'asc',
                      };

            write({
                page: null,
                sort: chosen.field,
                direction: chosen.direction,
            });
        },
        [defaultSort, sortableFields, sorting, write],
    );

    const setSearch = useCallback(
        (value: string) => write({ page: null, search: value === '' ? null : value }),
        [write],
    );

    return useMemo(
        () => ({
            page,
            perPage,
            sort: { field, direction },
            search,
            pagination,
            sorting,
            onPaginationChange,
            onSortingChange,
            setSearch,
        }),
        [
            page,
            perPage,
            field,
            direction,
            search,
            pagination,
            sorting,
            onPaginationChange,
            onSortingChange,
            setSearch,
        ],
    );
}
