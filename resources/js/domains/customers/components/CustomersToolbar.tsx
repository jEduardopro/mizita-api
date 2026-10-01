import { LayoutList, Search, Table2 } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { FilterChipRow } from '@/components/admin/filter-chips/FilterChipRow';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { CustomerRegistrationRange } from '../types';
import { CustomerRegistrationRangeFilter } from './CustomerRegistrationRangeFilter';

export const CUSTOMERS_VIEWS = ['table', 'list'] as const;

export type CustomersView = (typeof CUSTOMERS_VIEWS)[number];

function isCustomersView(value: string): value is CustomersView {
    return (CUSTOMERS_VIEWS as readonly string[]).includes(value);
}

type Props = {
    search: string;
    onSearchChange: (value: string) => void;
    registrationRange: CustomerRegistrationRange | null;
    onRegistrationRangeApply: (range: CustomerRegistrationRange) => void;
    onRegistrationRangeClear: () => void;
    today?: string;
    view: CustomersView;
    onViewChange: (view: CustomersView) => void;
};

export function CustomersToolbar({
    search,
    onSearchChange,
    registrationRange,
    onRegistrationRangeApply,
    onRegistrationRangeClear,
    today,
    view,
    onViewChange,
}: Props) {
    const { t } = useTranslation('admin');
    const searchId = useId();

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="relative flex-1">
                <Label htmlFor={searchId} className="sr-only">
                    {t('customers.search.label')}
                </Label>

                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />

                <Input
                    id={searchId}
                    type="search"
                    autoComplete="off"
                    placeholder={t('customers.search.placeholder')}
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    className="h-11 pl-9 text-base md:h-9 md:text-base"
                />
            </div>

            {today === undefined ? null : (
                <FilterChipRow aria-label={t('customers.filters.label')}>
                    <CustomerRegistrationRangeFilter
                        value={registrationRange}
                        onApply={onRegistrationRangeApply}
                        onClear={onRegistrationRangeClear}
                        today={today}
                    />
                </FilterChipRow>
            )}

            <ToggleGroup
                type="single"
                variant="outline"
                spacing={0}
                value={view}
                onValueChange={(value) => {
                    if (isCustomersView(value)) {
                        onViewChange(value);
                    }
                }}
                aria-label={t('customers.view.label')}
                className="hidden md:flex"
            >
                <ToggleGroupItem
                    value="table"
                    aria-label={t('customers.view.table')}
                    className="size-11 md:size-9"
                >
                    <Table2 aria-hidden="true" />
                </ToggleGroupItem>

                <ToggleGroupItem
                    value="list"
                    aria-label={t('customers.view.list')}
                    className="size-11 md:size-9"
                >
                    <LayoutList aria-hidden="true" />
                </ToggleGroupItem>
            </ToggleGroup>
        </div>
    );
}
