import { cn } from 'cn';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { WordmarkMark } from '@/components/shared/Wordmark';
import { GoogleCalendarIcon } from './GoogleCalendarIcon';

const MIZITA_NAME = 'Mizita';

type LaneDirection = 'toGoogle' | 'toMizita';

const LANE_ROW_CLASSES = {
    toGoogle: 'flex-row',
    toMizita: 'flex-row-reverse',
} as const satisfies Record<LaneDirection, string>;

const LANE_LINE_CLASSES = {
    toGoogle: 'border-primary',
    toMizita: 'border-dashed border-foreground/40',
} as const satisfies Record<LaneDirection, string>;

const LANE_ARROW_CLASSES = {
    toGoogle: '-ml-1.5 text-primary',
    toMizita: '-mr-1.5 text-foreground/50',
} as const satisfies Record<LaneDirection, string>;

const LANE_ARROWS = {
    toGoogle: ChevronRight,
    toMizita: ChevronLeft,
} as const satisfies Record<LaneDirection, unknown>;

type LaneProps = {
    label: string;
    direction: LaneDirection;
};

function SyncLane({ label, direction }: LaneProps) {
    const Arrow = LANE_ARROWS[direction];

    return (
        <span className="grid gap-1">
            <span className="text-center text-xs leading-tight text-pretty text-muted-foreground">{label}</span>

            <span className={cn('flex items-center', LANE_ROW_CLASSES[direction])}>
                <span className={cn('h-0 flex-1 border-t-2', LANE_LINE_CLASSES[direction])} />
                <Arrow aria-hidden="true" strokeWidth={2.5} className={cn('size-4 shrink-0', LANE_ARROW_CLASSES[direction])} />
            </span>
        </span>
    );
}

type NodeProps = {
    children: ReactNode;
};

function SyncNode({ children }: NodeProps) {
    return (
        <span className="grid h-12 min-w-12 place-items-center rounded-xl bg-background px-3 ring-1 ring-border">
            {children}
        </span>
    );
}

export function CalendarSyncDiagram() {
    const { t } = useTranslation('admin');

    return (
        <figure className="rounded-xl border border-border bg-muted/40 px-4 py-5 sm:px-6">
            <div aria-hidden="true" className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 sm:gap-5">
                <SyncNode>
                    <WordmarkMark name={MIZITA_NAME} size="sm" />
                </SyncNode>

                <span className="grid gap-3">
                    <SyncLane label={t('integrations.googleCalendar.about.flow.appointments')} direction="toGoogle" />
                    <SyncLane label={t('integrations.googleCalendar.about.flow.busyTime')} direction="toMizita" />
                </span>

                <SyncNode>
                    <GoogleCalendarIcon className="size-7" />
                </SyncNode>
            </div>

            <figcaption className="sr-only">{t('integrations.googleCalendar.about.flow.label')}</figcaption>
        </figure>
    );
}
