import type { OptionsFilterOption } from './OptionsFilterChip';

const VALUE_SEPARATOR = ', ';

function keepValue(value: string): string {
    return value;
}

export function selectedOptionsLabel(
    options: readonly OptionsFilterOption[],
    values: readonly string[],
    labelOfUnlisted: (value: string) => string = keepValue,
): string | null {
    if (values.length === 0) {
        return null;
    }

    const listed = options.filter((option) => values.includes(option.value)).map((option) => option.label);
    const unlisted = values
        .filter((value) => ! options.some((option) => option.value === value))
        .map(labelOfUnlisted);

    return [...listed, ...unlisted].join(VALUE_SEPARATOR);
}
