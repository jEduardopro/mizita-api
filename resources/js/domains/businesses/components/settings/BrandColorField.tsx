import { useTranslation } from 'react-i18next';
import { ColorSwatchField } from '@/components/form/ColorSwatchField';
import { BRAND_COLORS, type BrandColor } from '@/domains/businesses/types';
import { brandColorClasses } from './brand-color';

const FIELD_ID = 'brand-accent-color';

const COLOR_LABEL_KEYS = {
    ink: 'businessSettings.brandColor.colors.ink',
    red: 'businessSettings.brandColor.colors.red',
    orange: 'businessSettings.brandColor.colors.orange',
    amber: 'businessSettings.brandColor.colors.amber',
    purple: 'businessSettings.brandColor.colors.purple',
    blue: 'businessSettings.brandColor.colors.blue',
    sand: 'businessSettings.brandColor.colors.sand',
    slate: 'businessSettings.brandColor.colors.slate',
    teal: 'businessSettings.brandColor.colors.teal',
    green: 'businessSettings.brandColor.colors.green',
} as const satisfies Record<BrandColor, string>;

type Props = {
    value: BrandColor;
    onChange: (value: BrandColor) => void;
    error?: string;
};

export function BrandColorField({ value, onChange, error }: Props) {
    const { t } = useTranslation('admin');

    const options = BRAND_COLORS.map((color) => ({
        value: color,
        label: t(COLOR_LABEL_KEYS[color]),
        swatchClassName: brandColorClasses[color].accent,
    }));

    return (
        <ColorSwatchField
            id={FIELD_ID}
            label={t('businessSettings.brandColor.label')}
            options={options}
            value={value}
            onChange={onChange}
            hint={t('businessSettings.brandColor.hint')}
            error={error}
        />
    );
}
