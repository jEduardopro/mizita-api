import { cn } from 'cn';
import { Plus, UserRound } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { useCustomerCreation } from '@/hooks/use-customer-creation';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useBookableCustomerSearch, useRefreshBookableCustomers } from '../queries';
import { APPOINTMENT_CONTROL_HEIGHT, AppointmentFormRow } from './AppointmentFormRow';
import type { AppointmentFormController } from './use-appointment-form';

const SEARCH_DEBOUNCE_MS = 400;

type Props = {
    form: AppointmentFormController;
};

export function AppointmentCustomerField({ form }: Props) {
    const { t } = useTranslation('admin');
    const fieldId = useId();
    const listId = `${fieldId}-listbox`;
    const inputRef = useRef<HTMLInputElement>(null);
    const selected = form.values.customer;
    const [query, setQuery] = useState(selected?.name ?? '');
    const [open, setOpen] = useState(false);
    const debouncedQuery = useDebouncedValue(query, SEARCH_DEBOUNCE_MS);
    const { requestCreate } = useCustomerCreation();
    const refreshCustomers = useRefreshBookableCustomers();
    const error = form.errorFor('customer');
    const message = fieldMessage({ id: fieldId, error });

    const trimmedQuery = debouncedQuery.trim();
    const isSearching = trimmedQuery !== '' && trimmedQuery !== selected?.name;

    const { data, isPending } = useBookableCustomerSearch(trimmedQuery);

    const matches = isSearching ? (data ?? []) : [];
    const showAddRow = isSearching && ! isPending && matches.length === 0;

    function selectCustomer(customer: { id: string; name: string }) {
        form.update('customer', customer);
        setQuery(customer.name);
        setOpen(false);
    }

    function keepOpenOnAnchorInteraction(event: Event) {
        if (inputRef.current !== null && event.target === inputRef.current) {
            event.preventDefault();
        }
    }

    function startCustomerCreation() {
        inputRef.current?.blur();
        setOpen(false);

        requestCreate(trimmedQuery, (customer) => {
            selectCustomer(customer);
            refreshCustomers();
        });
    }

    return (
        <AppointmentFormRow
            icon={<UserRound />}
            label={t('calendar.appointment.form.customer.label')}
            htmlFor={fieldId}
            message={message}
        >
            <Popover open={open && isSearching} onOpenChange={setOpen}>
                <PopoverAnchor asChild>
                    <Input
                        ref={inputRef}
                        id={fieldId}
                        role="combobox"
                        aria-expanded={open}
                        aria-controls={listId}
                        aria-invalid={!! error}
                        aria-describedby={message?.id}
                        autoComplete="off"
                        placeholder={t('calendar.appointment.form.customer.placeholder')}
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOpen(true);

                            if (selected !== null) {
                                form.update('customer', null);
                            }
                        }}
                        onFocus={() => setOpen(true)}
                        className={cn('w-full', APPOINTMENT_CONTROL_HEIGHT)}
                    />
                </PopoverAnchor>

                <PopoverContent
                    align="start"
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    onInteractOutside={keepOpenOnAnchorInteraction}
                    className="w-(--radix-popover-trigger-width) gap-0 overflow-hidden p-0 shadow-lg"
                >
                    {isPending ? (
                        <p role="status" className="px-3 py-2.5 text-sm text-muted-foreground">
                            {t('calendar.appointment.form.customer.searching')}
                        </p>
                    ) : null}

                    <ul id={listId} role="listbox" aria-label={t('calendar.appointment.form.customer.label')}>
                        {matches.map((customer) => (
                            <li
                                key={customer.id}
                                role="option"
                                aria-selected={customer.id === selected?.id}
                                onClick={() => selectCustomer({ id: customer.id, name: customer.name })}
                                className="flex min-h-11 cursor-default flex-col justify-center gap-0.5 px-3 py-2 text-sm hover:bg-muted"
                            >
                                <span>{customer.name}</span>

                                {customer.email !== null ? (
                                    <span className="text-xs text-muted-foreground">{customer.email}</span>
                                ) : null}
                            </li>
                        ))}

                        {showAddRow ? (
                            <li
                                role="option"
                                aria-selected={false}
                                onClick={startCustomerCreation}
                                className="flex min-h-11 cursor-default items-center gap-2 px-3 py-2 text-sm font-medium text-primary hover:bg-muted"
                            >
                                <Plus aria-hidden="true" className="size-4" />

                                {t('calendar.appointment.form.customer.create')}
                            </li>
                        ) : null}
                    </ul>
                </PopoverContent>
            </Popover>
        </AppointmentFormRow>
    );
}
