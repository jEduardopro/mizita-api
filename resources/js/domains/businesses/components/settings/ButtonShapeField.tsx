import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { BUTTON_SHAPES, type ButtonShape } from '@/domains/businesses/types';

const FIELD_ID = 'button-shape';

const PREVIEW_CLASSES: Record<ButtonShape, string> = {
    pill: 'rounded-full',
    rounded: 'rounded-lg',
    rectangle: 'rounded-none',
};

const LABEL_KEYS = {
    pill: 'businessSettings.buttonShape.shapes.pill',
    rounded: 'businessSettings.buttonShape.shapes.rounded',
    rectangle: 'businessSettings.buttonShape.shapes.rectangle',
} as const satisfies Record<ButtonShape, string>;

type Props = {
    value: ButtonShape;
    onChange: (value: ButtonShape) => void;
    error?: string;
};

export function ButtonShapeField({ value, onChange, error }: Props) {
    const { t } = useTranslation('admin');

    const message = fieldMessage({ id: FIELD_ID, error });

    function selectShape(candidate: string): void {
        const shape = BUTTON_SHAPES.find((option) => option === candidate);

        if (shape !== undefined) {
            onChange(shape);
        }
    }

    return (
        <div className="grid gap-2">
            <span id={`${FIELD_ID}-label`} className="text-sm leading-none font-medium">
                {t('businessSettings.buttonShape.label')}
            </span>

            <RadioGroup
                value={value}
                onValueChange={selectShape}
                aria-labelledby={`${FIELD_ID}-label`}
                aria-describedby={message?.id}
                className="grid-cols-3 gap-2 sm:gap-3"
            >
                {BUTTON_SHAPES.map((shape) => {
                    const isSelected = shape === value;

                    return (
                        <label
                            key={shape}
                            htmlFor={`${FIELD_ID}-${shape}`}
                            className={cn(
                                'flex min-h-24 flex-col items-center justify-center gap-3 rounded-xl border p-3 text-center transition-colors has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50',
                                isSelected
                                    ? 'border-primary bg-surface-brand'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'h-7 w-full max-w-20 bg-foreground',
                                    PREVIEW_CLASSES[shape],
                                )}
                            />

                            <span className="text-xs font-medium text-pretty">
                                {t(LABEL_KEYS[shape])}
                            </span>

                            <RadioGroupItem id={`${FIELD_ID}-${shape}`} value={shape} className="sr-only" />
                        </label>
                    );
                })}
            </RadioGroup>

            <FieldMessage message={message} />
        </div>
    );
}
