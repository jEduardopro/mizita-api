import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ColorSwatchField } from '@/components/form/ColorSwatchField';
import { FieldRow } from '@/components/form/FieldRow';
import { FormField } from '@/components/form/FormField';
import { ImageField } from '@/components/form/ImageField';
import { NumberField } from '@/components/form/NumberField';
import { PriceField } from '@/components/form/PriceField';
import { TextareaField } from '@/components/form/TextareaField';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useInitialFocus } from '@/hooks/use-initial-focus';
import {
    SERVICE_COLORS,
    type ServiceColor,
    serviceColorClasses,
} from '@/lib/service-color';
import { SERVICE_IMAGE_MAXIMUM_BYTES, SERVICE_IMAGE_MIME_TYPES } from '../types';
import type { ServiceFormController } from './use-service-form';

const COLOR_LABEL_KEYS = {
    red: 'services.colors.red',
    orange: 'services.colors.orange',
    amber: 'services.colors.amber',
    purple: 'services.colors.purple',
    blue: 'services.colors.blue',
    sand: 'services.colors.sand',
    slate: 'services.colors.slate',
    teal: 'services.colors.teal',
    green: 'services.colors.green',
} as const satisfies Record<ServiceColor, string>;

type Props = {
    form: ServiceFormController;
    focusNameField: boolean;
};

export function ServiceDetailsFields({ form, focusNameField }: Props) {
    const { t } = useTranslation('admin');
    const nameRef = useInitialFocus<HTMLInputElement>(focusNameField);

    const colorOptions = SERVICE_COLORS.map((color) => ({
        value: color,
        label: t(COLOR_LABEL_KEYS[color]),
        swatchClassName: serviceColorClasses[color].bar,
    }));

    return (
        <div className="@container/details grid gap-5">
            <div className="grid gap-5 @2xl/details:grid-cols-[200px_minmax(0,1fr)] @2xl/details:items-start md:@min-[600px]/details:grid-cols-[200px_minmax(0,1fr)] md:@min-[600px]/details:items-start">
                <ImageField
                    id="service-image"
                    label={t('services.form.image.label')}
                    shownUrl={form.image.shownUrl}
                    onSelect={form.image.select}
                    onClear={form.image.clear}
                    accept={SERVICE_IMAGE_MIME_TYPES}
                    maximumBytes={SERVICE_IMAGE_MAXIMUM_BYTES}
                    hint={t('services.form.image.hint')}
                    messages={{
                        choose: t('services.form.image.choose'),
                        upload: t('services.form.image.upload'),
                        replace: t('services.form.image.replace'),
                        remove: t('services.form.image.remove'),
                        tooLarge: t('services.form.image.tooLarge'),
                        unsupported: t('services.form.image.unsupported'),
                    }}
                />

                <div className="grid gap-5">
                    <FormField
                        ref={nameRef}
                        id="service-name"
                        label={t('services.form.name.label')}
                        placeholder={t('services.form.name.placeholder')}
                        autoComplete="off"
                        required
                        maxLength={120}
                        value={form.values.name}
                        onChange={(event) => form.update('name', event.target.value)}
                        error={form.errorFor('name')}
                    />

                    <ColorSwatchField
                        id="service-color"
                        label={t('services.form.color.label')}
                        options={colorOptions}
                        value={form.values.color}
                        onChange={(value) => form.update('color', value)}
                        error={form.errorFor('color')}
                    />
                </div>
            </div>

            <TextareaField
                id="service-description"
                label={t('services.form.description.label')}
                placeholder={t('services.form.description.placeholder')}
                rows={4}
                value={form.values.description}
                onChange={(event) => form.update('description', event.target.value)}
                error={form.errorFor('description')}
            />

            <FieldRow columns={3}>
                <NumberField
                    id="service-duration"
                    label={t('services.form.duration.label')}
                    suffix={t('services.form.duration.suffix')}
                    maxLength={4}
                    required
                    value={form.values.durationMinutes}
                    onChange={(value) => form.update('durationMinutes', value)}
                    error={form.errorFor('durationMinutes')}
                />

                <NumberField
                    id="service-buffer"
                    label={t('services.form.buffer.label')}
                    suffix={t('services.form.duration.suffix')}
                    maxLength={4}
                    value={form.values.bufferMinutes}
                    onChange={(value) => form.update('bufferMinutes', value)}
                    hint={t('services.form.buffer.hint')}
                    error={form.errorFor('bufferMinutes')}
                    labelAdornment={
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    aria-label={t('services.form.buffer.help')}
                                    className="inline-flex size-6 items-center justify-center rounded-full text-muted-foreground outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                                >
                                    <Info aria-hidden="true" className="size-4" />
                                </button>
                            </TooltipTrigger>

                            <TooltipContent>{t('services.form.buffer.hint')}</TooltipContent>
                        </Tooltip>
                    }
                />

                <PriceField
                    id="service-price"
                    label={t('services.form.price.label')}
                    currencySymbol={t('services.currencySymbol')}
                    required
                    value={form.values.price}
                    onChange={(value) => form.update('price', value)}
                    hint={t('services.form.price.hint')}
                    error={form.errorFor('price')}
                />
            </FieldRow>
        </div>
    );
}
