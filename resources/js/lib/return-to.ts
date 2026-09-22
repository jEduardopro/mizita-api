export const RETURN_PARAMETER = 'from';

const QUERY_SEPARATOR = '?';

const PARAMETER_SEPARATOR = '&';

const PATH_PREFIX = '/';

const PROTOCOL_RELATIVE_PREFIX = '//';

const BACKSLASH = '\\';

const SCHEME_SEPARATOR = ':';

export function withReturnTo(url: string, origin: string): string {
    const parameters = new URLSearchParams({ [RETURN_PARAMETER]: origin });
    const separator = url.includes(QUERY_SEPARATOR) ? PARAMETER_SEPARATOR : QUERY_SEPARATOR;

    return `${url}${separator}${parameters.toString()}`;
}

export function safeReturnTo(value: string | null, fallback: string): string {
    if (value === null || ! value.startsWith(PATH_PREFIX)) {
        return fallback;
    }

    if (value.startsWith(PROTOCOL_RELATIVE_PREFIX)) {
        return fallback;
    }

    if (value.includes(BACKSLASH) || value.includes(SCHEME_SEPARATOR)) {
        return fallback;
    }

    return value;
}
