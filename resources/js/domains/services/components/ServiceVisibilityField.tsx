import { cn } from 'cn';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import type { ServiceActivation } from './use-service-activation';

type Props = {
    checked: boolean;
    onChange: (checked: boolean) => void;
    activation: ServiceActivation;
};

export function ServiceVisibilityField({ checked, onChange, activation }: Props) {
    const { t } = useTranslation('admin');
    const hintId = useId();

    return (
        <label
            className={cn(
                'flex items-start gap-3 rounded-xl border border-input p-3',
                activation.blocked && 'cursor-not-allowed bg-muted/40',
            )}
        >
            <input
                type="checkbox"
                checked={checked}
                disabled={activation.blocked}
                aria-describedby={hintId}
                onChange={(event) => onChange(event.target.checked)}
                className="mt-0.5 size-4.5 accent-primary disabled:cursor-not-allowed"
            />

            <span className="grid gap-1">
                <span
                    className={cn(
                        'text-sm font-medium',
                        activation.blocked && 'text-muted-foreground',
                    )}
                >
                    {t('services.form.visibility.label')}
                </span>

                <span id={hintId} className="text-xs text-pretty text-muted-foreground">
                    {activation.blocked
                        ? t('plan.services.activationBlocked', { limit: activation.limit })
                        : t('services.form.visibility.hint')}
                </span>
            </span>
        </label>
    );
}
