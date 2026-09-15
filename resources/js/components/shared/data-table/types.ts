export type DataTableStatus = 'pending' | 'error' | 'ready';

export type SortDirection = 'asc' | 'desc';

export type DataTableSort<TField extends string = string> = {
    field: TField;
    direction: SortDirection;
};

export const PAGE_SIZES = [10, 25, 50, 100] as const;

export type PageSize = (typeof PAGE_SIZES)[number];

export const DEFAULT_PAGE_SIZE: PageSize = 25;
