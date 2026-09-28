import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { usePublicBusinessPage } from '../../queries';
import type { PublicBusinessPage, PublicService, PublicTeamMember } from '../../types';
import { resolveBooking } from './booking-resolution';
import {
    BOOKING_SELECTION_KEYS,
    BOOKING_STEPS,
    bookingBackUrl,
    bookingStepsFor,
    bookingStepUrl,
    EMPTY_BOOKING_SELECTION,
    hasUnresolvedPin,
    isStepSkipped,
    nextStep,
    previousStep,
    selectionBefore,
    selectionThrough,
    stepPrerequisites,
    type BookingSelection,
    type BookingStep,
} from './booking-steps';

type BookingFlowNavigation = {
    slug: string;
    step: BookingStep;
    selection: BookingSelection;
    backUrl: string;
    urlFor(step: BookingStep, patch?: Partial<BookingSelection>): string;
    goTo(step: BookingStep, patch?: Partial<BookingSelection>): void;
    advance(patch: Partial<BookingSelection>): void;
};

export type PendingBookingFlow = BookingFlowNavigation & {
    status: 'loading' | 'error' | 'redirecting';
    retry(): void;
};

export type ReadyBookingFlow = BookingFlowNavigation & {
    status: 'ready';
    page: PublicBusinessPage;
    steps: BookingStep[];
    pinnedMember: PublicTeamMember | null;
    services: PublicService[];
    service: PublicService | null;
    staffMember: PublicTeamMember | null;
    startsAt: string | null;
    timezone: string;
};

export type BookingFlow = PendingBookingFlow | ReadyBookingFlow;

const FIRST_STEP: BookingStep = BOOKING_STEPS[0];

const LAST_STEP: BookingStep = BOOKING_STEPS[BOOKING_STEPS.length - 1];

function furthestReachableStep(selection: BookingSelection): BookingStep {
    const blocked = bookingStepsFor(selection).find((step) =>
        stepPrerequisites(step).some((key) => selection[key] === null),
    );

    if (blocked === undefined) {
        return LAST_STEP;
    }

    return previousStep(blocked, selection) ?? FIRST_STEP;
}

function reaches(step: BookingStep, furthest: BookingStep): boolean {
    return BOOKING_STEPS.indexOf(step) <= BOOKING_STEPS.indexOf(furthest);
}

function canonicalStep(step: BookingStep, selection: BookingSelection): BookingStep {
    const furthest = furthestReachableStep(selection);
    const wanted = isStepSkipped(step, selection) ? (nextStep(step, selection) ?? furthest) : step;

    return reaches(wanted, furthest) ? wanted : furthest;
}

function redirectUrlFor(
    slug: string,
    step: BookingStep,
    requested: BookingSelection,
    resolved: BookingSelection,
): string | null {
    const target = canonicalStep(step, resolved);

    if (target === step && ! hasUnresolvedPin(requested, resolved)) {
        return null;
    }

    return bookingStepUrl(slug, target, selectionThrough(target, resolved));
}

export function useBookingFlow(slug: string, step: BookingStep): BookingFlow {
    const { read } = useUrlQueryState();
    const { data: page, isPending, isError, refetch } = usePublicBusinessPage(slug);

    const selection = useMemo(() => {
        const current: BookingSelection = { ...EMPTY_BOOKING_SELECTION };

        for (const key of BOOKING_SELECTION_KEYS) {
            current[key] = read(key);
        }

        return current;
    }, [read]);

    const urlFor = useCallback(
        (target: BookingStep, patch: Partial<BookingSelection> = {}) =>
            bookingStepUrl(slug, target, { ...selectionBefore(target, selection), ...patch }),
        [selection, slug],
    );

    const goTo = useCallback(
        (target: BookingStep, patch: Partial<BookingSelection> = {}) => {
            router.visit(urlFor(target, patch), { preserveScroll: true });
        },
        [urlFor],
    );

    const advance = useCallback(
        (patch: Partial<BookingSelection>) => {
            const target = nextStep(step, { ...selection, ...patch });

            if (target !== null) {
                goTo(target, patch);
            }
        },
        [goTo, selection, step],
    );

    const navigation: BookingFlowNavigation = useMemo(
        () => ({
            slug,
            step,
            selection,
            backUrl: bookingBackUrl(slug, step, selection),
            urlFor,
            goTo,
            advance,
        }),
        [advance, goTo, selection, slug, step, urlFor],
    );

    const resolution = page === undefined ? null : resolveBooking(page, selection);
    const redirectUrl =
        resolution === null ? null : redirectUrlFor(slug, step, selection, resolution.selection);
    const isReachable = resolution !== null && redirectUrl === null;

    useEffect(() => {
        if (redirectUrl === null) {
            return;
        }

        router.visit(redirectUrl, { replace: true, preserveScroll: true });
    }, [redirectUrl]);

    const retry = useCallback(() => {
        void refetch();
    }, [refetch]);

    if (isPending) {
        return { ...navigation, status: 'loading', retry };
    }

    if (isError) {
        return { ...navigation, status: 'error', retry };
    }

    if (! isReachable) {
        return { ...navigation, status: 'redirecting', retry };
    }

    return {
        ...navigation,
        status: 'ready',
        page,
        steps: bookingStepsFor(resolution.selection),
        pinnedMember: resolution.pinnedMember,
        services: resolution.services,
        service: resolution.service,
        staffMember: resolution.staffMember,
        startsAt: selection.at,
        timezone: page.timezone,
    };
}
