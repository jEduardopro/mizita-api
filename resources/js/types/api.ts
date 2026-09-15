export type ApiWarning = {
    code: string;
    message: string;
};

export type ApiFailureBody = {
    message: string;
    code: string;
    warnings?: ApiWarning[];
};

export type ApiValidationFailureBody = {
    message: string;
    errors: Record<string, string[]>;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type Paginated<TItem> = {
    data: TItem[];
    meta: PaginationMeta;
};
