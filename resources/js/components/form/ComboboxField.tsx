import { cn } from 'cn';
import { Check, ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { useCombobox, type ComboboxOption } from '@/components/form/use-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type { ComboboxOption };

/** Where the list of choices is up to. The panel draws a state for each. */
export type ComboboxOptionsStatus = 'pending' | 'error' | 'ready';

type Messages = {
    /** Nothing matched what was typed. */
    empty: string;
    /** The list itself could not be loaded. */
    optionsError: string;
    /** The action that tries loading the list again. */
    retry: string;
    /** How many rows are showing, announced to a screen reader. */
    results: string;
};

/** Three rows of roughly option height, so the panel opens at its real size. */
const SKELETON_ROWS = [0, 1, 2];

type PanelProps = {
    listId: string;
    /** Names the listbox, which is a different element from the input. */
    label: string;
    options: readonly ComboboxOption[];
    /** The chosen value, so the row that holds it can be ticked. */
    selectedValue: string | null;
    /** The virtually focused row, or -1. */
    activeIndex: number;
    optionId: (index: number) => string;
    onSelect: (option: ComboboxOption) => void;
    status: ComboboxOptionsStatus;
    emptyMessage: string;
};

/**
 * The open list: the rows themselves, or what is standing in for them while they
 * load. Failure is not drawn here — see the field below.
 */
function ComboboxPanel({
    listId,
    label,
    options,
    selectedValue,
    activeIndex,
    optionId,
    onSelect,
    status,
    emptyMessage,
}: PanelProps) {
    return (
        <div
            aria-busy={status === 'pending'}
            className="overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
        >
            {status === 'pending' ? (
                <div className="grid gap-2 p-2.5">
                    {SKELETON_ROWS.map((row) => (
                        <span
                            key={row}
                            aria-hidden="true"
                            className="h-6 rounded-sm bg-muted motion-safe:animate-pulse"
                        />
                    ))}
                </div>
            ) : null}

            {status === 'ready' && options.length === 0 ? (
                <p role="status" className="px-3 py-2.5 text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            ) : null}

            <ul
                id={listId}
                role="listbox"
                aria-label={label}
                // Choosing a row must not move focus out of the box: the caret
                // stays where the person is typing, and the blur that would close
                // the list before the click landed never happens.
                onMouseDown={(event) => event.preventDefault()}
                className="max-h-[min(18rem,45svh)] overflow-y-auto overscroll-contain"
            >
                {options.map((option, index) => (
                    <li
                        key={option.value}
                        id={optionId(index)}
                        role="option"
                        aria-selected={option.value === selectedValue}
                        onClick={() => onSelect(option)}
                        className={cn(
                            'flex min-h-11 cursor-default items-center gap-2 px-3 py-2.5 text-base',
                            index === activeIndex ? 'bg-muted text-foreground' : undefined,
                        )}
                    >
                        <span className="min-w-0 flex-1">{option.label}</span>

                        {option.value === selectedValue ? (
                            <Check aria-hidden="true" className="size-4 shrink-0 text-primary" />
                        ) : null}
                    </li>
                ))}
            </ul>
        </div>
    );
}

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type' | 'role' | 'list'> & {
    id: string;
    /** The field's name, pre-translated. */
    label: string;
    /** The choices, already in the order they should be offered. */
    options: readonly ComboboxOption[];
    /** The selected option's `value`, or null. */
    value: string | null;
    onChange: (value: string | null) => void;
    optionsStatus: ComboboxOptionsStatus;
    onRetryOptions?: () => void;
    /** Every string this field can say, pre-translated by the caller. */
    messages: Messages;
    error?: string;
    hint?: string;
};

/**
 * A text field that filters a list of choices, where only a listed choice counts
 * as a value.
 *
 * The list is **in flow**, drawn directly under the input, rather than floated in
 * a layer anchored to it. On a phone with the keyboard up there is no room for an
 * anchored popover to be honest: it either covers the field being typed into or
 * flips above it and covers the label. Pushing the rest of the form down costs a
 * scroll and hides nothing.
 *
 * Typing matches without accents, which is the whole point in Spanish —
 * `barberia` finds `Barbería` — and every string it can say arrives already
 * translated, because this folder is audience-agnostic and must not reach into a
 * locale namespace of its own.
 */
export function ComboboxField({
    id,
    label,
    options,
    value,
    onChange,
    optionsStatus,
    onRetryOptions,
    messages,
    error,
    hint,
    className,
    ...props
}: Props) {
    const combobox = useCombobox({ id, options, value, onChange });
    const message = fieldMessage({ id, error, hint });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <div ref={combobox.rootRef} onBlur={combobox.onBlur} className="grid gap-1.5">
                <div className="relative">
                    <Input
                        {...props}
                        id={id}
                        role="combobox"
                        aria-expanded={combobox.open}
                        aria-controls={combobox.listId}
                        aria-autocomplete="list"
                        aria-activedescendant={combobox.activeOptionId}
                        aria-invalid={!! error}
                        aria-describedby={message?.id}
                        // The browser has nothing useful to suggest for a list the
                        // server owns, and its own panel would cover this one.
                        autoComplete="off"
                        autoCorrect="off"
                        spellCheck={false}
                        value={combobox.query}
                        onChange={combobox.onQueryChange}
                        onKeyDown={combobox.onKeyDown}
                        onClick={combobox.openList}
                        className={cn('h-11 pr-9 text-base md:text-base', className)}
                    />

                    <ChevronDown
                        aria-hidden="true"
                        className={cn(
                            'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground',
                            'motion-safe:transition-transform motion-safe:duration-200',
                            combobox.open ? 'rotate-180' : undefined,
                        )}
                    />
                </div>

                {combobox.open ? (
                    <ComboboxPanel
                        listId={combobox.listId}
                        label={label}
                        options={combobox.filteredOptions}
                        selectedValue={value}
                        activeIndex={combobox.activeIndex}
                        optionId={combobox.optionId}
                        onSelect={combobox.selectOption}
                        status={optionsStatus}
                        emptyMessage={messages.empty}
                    />
                ) : null}

                {/*
                 * A list that failed to load is said outside the panel, and stays
                 * said whether or not the panel is open. Inside it, the retry
                 * button would exist only while the list was showing — which is
                 * exactly when a keyboard cannot reach it, since tabbing to it
                 * would close the thing it sits in.
                 */}
                {optionsStatus === 'error' ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-xs text-muted-foreground">{messages.optionsError}</p>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onRetryOptions}
                            className="h-11 px-3"
                        >
                            {messages.retry}
                        </Button>
                    </div>
                ) : null}
            </div>

            <span role="status" className="sr-only">
                {combobox.open ? messages.results : ''}
            </span>

            <FieldMessage message={message} />
        </div>
    );
}
