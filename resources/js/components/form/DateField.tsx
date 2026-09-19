import type { DatePartLabels } from '@/components/form/date-format';
import { DatePicker, type DatePickerMessages } from '@/components/form/DatePicker';
import { fieldMessage, FieldMessage, type HintTone } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    format: DatePartLabels;
    messages: DatePickerMessages;
    clearable?: boolean;
    min?: string;
    max?: string;
    error?: string;
    hint?: string;
    hintTone?: HintTone;
};

export function DateField({
    id,
    label,
    value,
    onChange,
    format,
    messages,
    clearable = false,
    min,
    max,
    error,
    hint,
    hintTone,
}: Props) {
    const message = fieldMessage({ id, error, hint, hintTone });
    const labelId = `${id}-label`;

    return (
        <div className="grid gap-2">
            <Label id={labelId} htmlFor={id}>
                {label}
            </Label>

            <DatePicker
                id={id}
                value={value}
                onChange={onChange}
                format={format}
                messages={messages}
                clearable={clearable}
                min={min}
                max={max}
                invalid={!! error}
                describedBy={message?.id}
                labelledBy={`${labelId} ${id}`}
            />

            <FieldMessage message={message} />
        </div>
    );
}
