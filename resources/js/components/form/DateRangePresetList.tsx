import { cn } from 'cn';
import {
    isSameRange,
    type DateRangePresetLabelKey,
    type DateRangeValue,
    type ResolvedDateRangePreset,
} from '@/components/form/date-range';

type Props = {
    label: string;
    presets: readonly ResolvedDateRangePreset[];
    presetLabels: Record<DateRangePresetLabelKey, string>;
    draftRange: DateRangeValue | null;
    onSelect: (range: DateRangeValue) => void;
    className?: string;
};

const PRESET_LIST_CLASS =
    'flex gap-2 overflow-x-auto border-b border-border p-3 [contain:inline-size] [scrollbar-width:none] md:w-40 md:shrink-0 md:flex-col md:gap-0.5 md:overflow-visible md:border-r md:border-b-0 md:[contain:none] [&::-webkit-scrollbar]:hidden';

const PRESET_CLASS =
    'flex min-h-11 w-full items-center rounded-full border border-border px-4 text-left text-sm whitespace-nowrap text-foreground/80 transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 aria-pressed:border-transparent aria-pressed:bg-muted aria-pressed:font-medium aria-pressed:text-foreground md:min-h-9 md:rounded-md md:border-transparent md:px-3';

export function DateRangePresetList({ label, presets, presetLabels, draftRange, onSelect, className }: Props) {
    return (
        <ul aria-label={label} className={cn(PRESET_LIST_CLASS, className)}>
            {presets.map((preset) => (
                <li key={preset.id} className="shrink-0">
                    <button
                        type="button"
                        aria-pressed={isSameRange(preset.range, draftRange)}
                        onClick={() => onSelect(preset.range)}
                        className={PRESET_CLASS}
                    >
                        {presetLabels[preset.labelKey]}
                    </button>
                </li>
            ))}
        </ul>
    );
}
