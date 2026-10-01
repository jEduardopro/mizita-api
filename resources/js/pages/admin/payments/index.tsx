import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { OptionsFilterOption } from '@/components/admin/filter-chips/OptionsFilterChip';
import { comboboxOptionsStatus, type ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { DataTableSkeleton } from '@/components/shared/data-table/DataTableSkeleton';
import { useBusinessTimezone } from '@/domains/businesses/queries';
import { useCustomers, useCustomersById } from '@/domains/customers/queries';
import type { Customer } from '@/domains/customers/types';
import { PaymentCustomerFilter } from '@/domains/payments/components/payment-history/PaymentCustomerFilter';
import { PaymentHistoryTabs } from '@/domains/payments/components/payment-history/PaymentHistoryTabs';
import { SalesTable } from '@/domains/payments/components/payment-history/SalesTable';
import { TransactionsTable } from '@/domains/payments/components/payment-history/TransactionsTable';
import { usePaymentCustomerFilter } from '@/domains/payments/components/payment-history/use-payment-customer-filter';
import { usePaymentHistoryTab } from '@/domains/payments/components/payment-history/use-payment-history-tab';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { AdminLayout } from '@/layouts/AdminLayout';
import { isoDateIn } from '@/lib/timezone';

const CUSTOMER_SEARCH_DEBOUNCE_MS = 300;

const CUSTOMER_OPTIONS_PAGE_SIZE = 20;

const FIRST_PAGE = 1;

const HISTORY_COLUMN_COUNT = 5;

type CustomerFilterChoices = {
    options: OptionsFilterOption[];
    status: ComboboxOptionsStatus;
    retry: () => void;
    setSearchTerm: (term: string) => void;
    firstSelectedName: string | null;
};

function customerOption(customer: Customer): OptionsFilterOption {
    return { value: customer.id, label: customer.name };
}

function useCustomerFilterChoices(selectedIds: readonly string[]): CustomerFilterChoices {
    const [searchTerm, setSearchTerm] = useState('');
    const search = useDebouncedValue(searchTerm.trim(), CUSTOMER_SEARCH_DEBOUNCE_MS);

    const matches = useCustomers({
        page: FIRST_PAGE,
        per_page: CUSTOMER_OPTIONS_PAGE_SIZE,
        sort: 'name',
        direction: 'asc',
        search: search === '' ? undefined : search,
    });

    const selected = useCustomersById(selectedIds);

    const selectedCustomers = selected.flatMap((result) => (result.data === undefined ? [] : [result.data]));
    const matchingCustomers = matches.data?.data ?? [];
    const unselectedMatches = matchingCustomers.filter((customer) => ! selectedIds.includes(customer.id));
    const listedCustomers = search === '' ? [...selectedCustomers, ...unselectedMatches] : matchingCustomers;

    const firstSelectedId = selectedIds[0];
    const firstSelectedName =
        [...selectedCustomers, ...matchingCustomers].find((customer) => customer.id === firstSelectedId)?.name ??
        null;

    const { refetch } = matches;
    const retry = useCallback(() => void refetch(), [refetch]);

    return {
        options: listedCustomers.map(customerOption),
        status: comboboxOptionsStatus(matches.isPending, matches.isError),
        retry,
        setSearchTerm,
        firstSelectedName,
    };
}

export default function PaymentsIndex() {
    const { t } = useTranslation('admin');
    const { tab, setTab } = usePaymentHistoryTab();
    const customerFilter = usePaymentCustomerFilter();
    const customerChoices = useCustomerFilterChoices(customerFilter.values);
    const businessTimezone = useBusinessTimezone();
    const businessToday = businessTimezone === null ? null : isoDateIn(businessTimezone, new Date());

    const customerChip = (
        <PaymentCustomerFilter
            options={customerChoices.options}
            status={customerChoices.status}
            onRetry={customerChoices.retry}
            onSearchChange={customerChoices.setSearchTerm}
            value={customerFilter.values}
            firstSelectedName={customerChoices.firstSelectedName}
            onApply={customerFilter.setValues}
            onClear={() => customerFilter.setValues([])}
        />
    );

    const loading = <DataTableSkeleton columns={HISTORY_COLUMN_COUNT} />;
    const isBusinessClockKnown = businessTimezone !== null && businessToday !== null;

    return (
        <AdminLayout title={t('payments.history.title')} frame="viewport">
            <PaymentHistoryTabs
                value={tab}
                onValueChange={setTab}
                sales={
                    isBusinessClockKnown ? (
                        <SalesTable timezone={businessTimezone} today={businessToday} customerFilter={customerChip} />
                    ) : (
                        loading
                    )
                }
                transactions={
                    isBusinessClockKnown ? (
                        <TransactionsTable
                            timezone={businessTimezone}
                            today={businessToday}
                            customerFilter={customerChip}
                        />
                    ) : (
                        loading
                    )
                }
            />
        </AdminLayout>
    );
}
