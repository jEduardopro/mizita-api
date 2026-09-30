import { useId, useMemo } from 'react';
import { Area, AreaChart, CartesianGrid, LabelList, ReferenceDot, XAxis, YAxis } from 'recharts';
import { useTranslation } from 'react-i18next';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { formatFixedMoneyFromCents } from '@/lib/money';
import type { DailyCollection } from '../types';
import { formatLongDate, formatShortWeekday } from './statistics-format';

type Props = {
    days: DailyCollection[];
    today: string;
};

const SERIES_KEY = 'collected';

const CHART_MARGIN = { top: 28, right: 16, bottom: 0, left: 16 };

const LABEL_FONT_SIZE = 11;

const LABEL_OFFSET = 10;

const TODAY_DOT_RADIUS = 5;

const DAY_DOT_RADIUS = 3;

const FILL_TOP_OPACITY = 0.32;

const FILL_BOTTOM_OPACITY = 0.02;

const UNSAFE_ID_CHARACTERS = /[^a-zA-Z0-9_-]/g;

function lowestOf(dataMin: number): number {
    return Math.min(0, dataMin);
}

export function SalesTrendChart({ days, today }: Props) {
    const { t, i18n } = useTranslation('admin');
    const locale = i18n.language;
    const gradientId = `sales-trend-${useId().replace(UNSAFE_ID_CHARACTERS, '')}`;

    const config = useMemo<ChartConfig>(
        () => ({ [SERIES_KEY]: { label: t('statistics.lastSevenDays.series'), color: 'var(--success)' } }),
        [t],
    );

    const points = useMemo(
        () => days.map((day) => ({ date: day.date, [SERIES_KEY]: day.collected_cents })),
        [days],
    );

    const todayPoint = days.find((day) => day.date === today);
    const dayLabel = (date: string) =>
        date === today ? t('statistics.lastSevenDays.today') : formatShortWeekday(date, locale);

    return (
        <>
            <ChartContainer
                config={config}
                role="img"
                aria-label={t('statistics.lastSevenDays.summary')}
                className="aspect-auto h-56 w-full [&_.recharts-label-list]:hidden sm:[&_.recharts-label-list]:block"
            >
                <AreaChart data={points} margin={CHART_MARGIN} accessibilityLayer={false}>
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={`var(--color-${SERIES_KEY})`} stopOpacity={FILL_TOP_OPACITY} />
                            <stop offset="100%" stopColor={`var(--color-${SERIES_KEY})`} stopOpacity={FILL_BOTTOM_OPACITY} />
                        </linearGradient>
                    </defs>

                    <CartesianGrid vertical={false} />

                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={10}
                        interval={0}
                        tickFormatter={(date: string) => dayLabel(date)}
                    />

                    <YAxis hide domain={[lowestOf, 'auto']} />

                    <ChartTooltip
                        cursor={false}
                        content={
                            <ChartTooltipContent
                                indicator="line"
                                labelFormatter={(label) => (typeof label === 'string' ? formatLongDate(label, locale) : label)}
                                formatter={(value) => (
                                    <span className="flex w-full items-center justify-between gap-3">
                                        <span className="text-muted-foreground">{t('statistics.lastSevenDays.series')}</span>
                                        <span className="font-medium text-foreground tabular-nums">
                                            {typeof value === 'number' ? formatFixedMoneyFromCents(value) : String(value)}
                                        </span>
                                    </span>
                                )}
                            />
                        }
                    />

                    <Area
                        dataKey={SERIES_KEY}
                        type="monotone"
                        stroke={`var(--color-${SERIES_KEY})`}
                        strokeWidth={2}
                        fill={`url(#${gradientId})`}
                        dot={{ r: DAY_DOT_RADIUS, fill: 'var(--card)', strokeWidth: 2 }}
                        activeDot={{ r: TODAY_DOT_RADIUS }}
                        isAnimationActive={false}
                    >
                        <LabelList
                            dataKey={SERIES_KEY}
                            position="top"
                            offset={LABEL_OFFSET}
                            fontSize={LABEL_FONT_SIZE}
                            className="fill-muted-foreground tabular-nums"
                            formatter={(value) => (typeof value === 'number' ? formatFixedMoneyFromCents(value) : value)}
                        />
                    </Area>

                    {todayPoint === undefined ? null : (
                        <ReferenceDot
                            x={todayPoint.date}
                            y={todayPoint.collected_cents}
                            r={TODAY_DOT_RADIUS}
                            fill={`var(--color-${SERIES_KEY})`}
                            stroke="var(--card)"
                            strokeWidth={2}
                        />
                    )}
                </AreaChart>
            </ChartContainer>

            <ul className="sr-only">
                {days.map((day) => (
                    <li key={day.date}>
                        {formatLongDate(day.date, locale)}: {formatFixedMoneyFromCents(day.collected_cents)}
                    </li>
                ))}
            </ul>
        </>
    );
}
