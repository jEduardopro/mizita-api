import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { usePublicBusinessPage } from '../../queries';
import type { PublicBusinessPage, PublicService, PublicTeamMember } from '../../types';
import {
    BOOKING_SELECTION_KEYS,
    BOOKING_STEPS,
    bookingBackUrl,
    bookingStepUrl,
    EMPTY_BOOKING_SELECTION,
    previousStep,
    selectionBefore,
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
};

export type PendingBookingFlow = BookingFlowNavigation & {
    status: 'loading' | 'error' | 'redirecting';
    retry(): void;
};

export type ReadyBookingFlow = BookingFlowNavigation & {
    status: 'ready';
    page: PublicBusinessPage;
    service: PublicService | null;
    staffMember: PublicTeamMember | null;
    startsAt: string | null;
    timezone: string;
};

export type BookingFlow = PendingBookingFlow | ReadyBookingFlow;

function serviceIn(page: PublicBusinessPage, serviceId: string | null): PublicService | null {
    return page.services.find((service) => service.id === serviceId) ?? null;
}

function staffMemberIn(
    page: PublicBusinessPage,
    service: PublicService | null,
    staffId: string | null,
): PublicTeamMember | null {
    if (service === null || staffId === null || ! service.staff_ids.includes(staffId)) {
        return null;
    }

    return page.team.find((member) => member.id === staffId) ?? null;
}

const FIRST_STEP: BookingStep = BOOKING_STEPS[0];

const LAST_STEP: BookingStep = BOOKING_STEPS[BOOKING_STEPS.length - 1];

function furthestReachableStep(selection: BookingSelection): BookingStep {
    const blocked = BOOKING_STEPS.find((step) =>
        stepPrerequisites(step).some((key) => selection[key] === null),
    );

    if (blocked === undefined) {
        return LAST_STEP;
    }

    return previousStep(blocked) ?? FIRST_STEP;
}

function reaches(step: BookingStep, furthest: BookingStep): boolean {
    return BOOKING_STEPS.indexOf(step) <= BOOKING_STEPS.indexOf(furthest);
}

function redirectUrlFor(
    page: PublicBusinessPage | undefined,
    slug: string,
    step: BookingStep,
    selection: BookingSelection,
): string | null {
    if (page === undefined) {
        return null;
    }

    const furthest = furthestReachableStep(selection);

    if (reaches(step, furthest)) {
        return null;
    }

    return bookingStepUrl(slug, furthest, selectionBefore(furthest, selection));
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

    const navigation: BookingFlowNavigation = useMemo(
        () => ({
            slug,
            step,
            selection,
            backUrl: bookingBackUrl(slug, step, selection),
            urlFor,
            goTo,
        }),
        [goTo, selection, slug, step, urlFor],
    );

    const service = page === undefined ? null : serviceIn(page, selection.service);
    const staffMember = page === undefined ? null : staffMemberIn(page, service, selection.staff);

    const resolved: BookingSelection = {
        service: service?.id ?? null,
        staff: staffMember?.id ?? null,
        at: selection.at,
    };

    const redirectUrl = redirectUrlFor(page, slug, step, resolved);
    const isReachable = page !== undefined && redirectUrl === null;

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
        service,
        staffMember,
        startsAt: selection.at,
        timezone: page.timezone,
    };
}
