export const PAGE_PARAMETER = 'page';

export const FROM_PARAMETER = 'from';

export const TO_PARAMETER = 'to';

export const RANGE_PARAMETER = 'range';

export const ALL_DATES = 'all';

export const CUSTOMER_IDS_PARAMETER = 'customer_ids';

export const REFERENCE_PARAMETER = 'reference';

export const STATUSES_PARAMETER = 'statuses';

export const TYPES_PARAMETER = 'types';

export const METHODS_PARAMETER = 'methods';

const TABLE_PARAMETERS = [PAGE_PARAMETER, 'per_page', 'sort', 'direction'] as const;

const FILTER_PARAMETERS = [
    FROM_PARAMETER,
    TO_PARAMETER,
    RANGE_PARAMETER,
    CUSTOMER_IDS_PARAMETER,
    REFERENCE_PARAMETER,
    STATUSES_PARAMETER,
    TYPES_PARAMETER,
    METHODS_PARAMETER,
] as const;

type QueryPatch = Record<string, string | null>;

function clearedPatch(parameters: readonly string[]): QueryPatch {
    return Object.fromEntries(parameters.map((parameter) => [parameter, null]));
}

export const TAB_STATE_RESET: QueryPatch = clearedPatch([...TABLE_PARAMETERS, ...FILTER_PARAMETERS]);

export const EVERY_FILTER_CLEARED: QueryPatch = {
    ...clearedPatch([PAGE_PARAMETER, ...FILTER_PARAMETERS]),
    [RANGE_PARAMETER]: ALL_DATES,
};
