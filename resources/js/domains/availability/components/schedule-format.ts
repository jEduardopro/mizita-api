import { formatTimeOfDay } from '@/lib/time';
import type { ScheduleRule } from '../types';

const RANGE_SEPARATOR = ' - ';

const INTERVAL_SEPARATOR = ', ';

export function formatScheduleRules(rules: readonly ScheduleRule[]): string {
    return rules
        .map((rule) => `${formatTimeOfDay(rule.starts_at)}${RANGE_SEPARATOR}${formatTimeOfDay(rule.ends_at)}`)
        .join(INTERVAL_SEPARATOR);
}
