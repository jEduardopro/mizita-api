import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    ACTIVITY_SCREEN_HEIGHT,
    ACTIVITY_SCREEN_WIDTH,
    ActivityScreen,
} from '@/components/shared/hero/ActivityScreen';
import {
    AGENDA_SCREEN_HEIGHT,
    AGENDA_SCREEN_WIDTH,
    AgendaColumn,
    FIRST_FREE_MINUTE,
    formatAgendaTime,
} from '@/components/shared/hero/AgendaColumn';
import { PHONE_STATUS_BAR, PhoneFrame } from '@/components/shared/hero/PhoneFrame';

/**
 * The hero's visual: two overlapping devices showing real product screens, with
 * two chips floating off them.
 *
 * Everything here is drawn — no photograph, no video, no binary asset. What the
 * page is selling is an agenda that already knows what is taken, so the hero
 * shows exactly that instead of a stock desk.
 *
 * The whole thing is decorative. It repeats nothing the headline beside it does
 * not already say, and it holds nothing focusable, so it is hidden from
 * assistive technology in one place at the root rather than piece by piece.
 *
 * Two audiences render it now — the public landing hero and the `auth` signup
 * screen — which is what moved it into `shared/`. That makes the scale steps
 * below load-bearing in two places at once: the landing page gives it a column
 * of its own on a page with nothing else in that row, while the signup screen
 * puts it under a headline in the narrower half of a split. Change a step and
 * check both, not just the one you are working on.
 *
 * It keeps reading the `public` namespace. The chips are marketing copy, not
 * chrome, and i18next bundles every namespace statically, so borrowing it from
 * another audience costs nothing at runtime.
 */

/**
 * The stage the pieces are laid out on, in px. Every position below is measured
 * against it, and the stage as a whole is scaled to whatever width the column
 * has — one scale for the composition, never one per piece, which is what keeps
 * the agenda's single-pixel hour rules crisp.
 */
const STAGE_WIDTH = 440;
const STAGE_HEIGHT = 520;

const AGENDA_PHONE = {
    left: 16,
    top: 12,
    screenWidth: AGENDA_SCREEN_WIDTH,
    screenHeight: PHONE_STATUS_BAR + AGENDA_SCREEN_HEIGHT,
};

const ACTIVITY_PHONE = {
    left: 204,
    top: 140,
    screenWidth: ACTIVITY_SCREEN_WIDTH,
    screenHeight: PHONE_STATUS_BAR + ACTIVITY_SCREEN_HEIGHT,
};

export function HeroCollage() {
    const { t, i18n } = useTranslation('public');

    return (
        /*
         * The wrapper reserves real layout height at the scaled size, so the
         * hero's grid row is the height the collage actually occupies. Clipping
         * is structural rather than cosmetic: at any viewport the stage is the
         * only thing that could push the page sideways, and it cannot leave this
         * box.
         */
        <div
            aria-hidden="true"
            className="relative mx-auto h-[calc(520px*var(--s))] w-[calc(440px*var(--s))] overflow-hidden [--s:0.63] min-[375px]:[--s:0.74] sm:[--s:0.9] lg:[--s:0.94]"
        >
            <div
                className="absolute top-0 left-0 origin-top-left scale-[var(--s)]"
                style={{ width: STAGE_WIDTH, height: STAGE_HEIGHT }}
            >
                {/*
                 * Each piece drifts on its own clock: two directions, four
                 * durations and a negative delay apiece, so nothing ever peaks
                 * in unison and the group never looks like one moving object.
                 * `rotate` is its own CSS property and the keyframes animate
                 * `translate`, so the tilt and the drift never overwrite one
                 * another.
                 */}
                <div
                    className="absolute -rotate-3 motion-safe:animate-float-slow"
                    style={{
                        left: AGENDA_PHONE.left,
                        top: AGENDA_PHONE.top,
                        animationDelay: '-1.4s',
                    }}
                >
                    <PhoneFrame
                        screenWidth={AGENDA_PHONE.screenWidth}
                        screenHeight={AGENDA_PHONE.screenHeight}
                    >
                        <AgendaColumn />
                    </PhoneFrame>
                </div>

                <div
                    className="absolute rotate-[4.5deg] motion-safe:animate-sink-slow"
                    style={{
                        left: ACTIVITY_PHONE.left,
                        top: ACTIVITY_PHONE.top,
                        animationDelay: '-0.6s',
                    }}
                >
                    <PhoneFrame
                        screenWidth={ACTIVITY_PHONE.screenWidth}
                        screenHeight={ACTIVITY_PHONE.screenHeight}
                    >
                        <ActivityScreen />
                    </PhoneFrame>
                </div>

                {/*
                 * The chips are the density the composition needs and the first
                 * thing to go when there is no room: below `lg` the hero stacks
                 * into one column and two phones are already enough.
                 */}
                <div
                    className="absolute hidden -rotate-2 lg:block motion-safe:animate-float-fast"
                    style={{
                        left: AGENDA_PHONE.left + 230,
                        top: 30,
                        width: 172,
                        animationDelay: '-2.2s',
                    }}
                >
                    <p className="flex items-center gap-2 rounded-xl border border-border bg-card px-3 py-2.5 text-[0.6875rem] leading-snug font-medium shadow-xl shadow-foreground/10 dark:shadow-black/40">
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                            <Check className="size-3.5" />
                        </span>
                        {t('heroCollage.chips.confirmed')}
                    </p>
                </div>

                <div
                    className="absolute hidden rotate-3 lg:block motion-safe:animate-sink-medium"
                    style={{ left: 8, top: 412, animationDelay: '-3.1s' }}
                >
                    <p className="flex items-center gap-2 rounded-full border border-border bg-card py-2 pr-4 pl-3 text-[0.6875rem] font-medium tabular-nums shadow-xl shadow-foreground/10 dark:shadow-black/40">
                        <span className="size-1.5 rounded-[2px] bg-primary" />
                        {t('heroCollage.chips.openSlot', {
                            time: formatAgendaTime(i18n.language, FIRST_FREE_MINUTE),
                        })}
                    </p>
                </div>
            </div>
        </div>
    );
}

