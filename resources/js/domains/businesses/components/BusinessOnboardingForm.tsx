import { router } from '@inertiajs/react';
import type { TFunction } from 'i18next';
import { LoaderCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    ComboboxField,
    type ComboboxOption,
    type ComboboxOptionsStatus,
} from '@/components/form/ComboboxField';
import type { HintTone } from '@/components/form/FieldMessage';
import { FormAlert } from '@/components/form/FormAlert';
import { FormField } from '@/components/form/FormField';
import { PhoneField } from '@/components/form/PhoneField';
import { Button } from '@/components/ui/button';
import {
    useBusinessNameAvailability,
    useCreateBusiness,
    type NameStatus,
} from '@/domains/businesses/queries';
import type { CreateBusinessPayload } from '@/domains/businesses/types';
import { useServerErrors } from '@/hooks/use-server-errors';
import type { FieldErrors } from '@/lib/http';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import { resolvedTimezone } from '@/lib/timezone';

/** Where the person lands once a business exists and the 403s stop. */
const DASHBOARD_URL = '/dashboard';

/** The product sells in Mexico first, so the prefix starts there. */
const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

/**
 * A country's name is copy and its dial code is a fact, so they come from
 * different places and are joined here. The map is exhaustive by type: adding a
 * country to `SUPPORTED_PHONE_COUNTRIES` fails this file until it has a name.
 */
const COUNTRY_NAME_KEYS = {
    MX: 'onboarding.phone.countries.MX',
    US: 'onboarding.phone.countries.US',
} as const satisfies Record<PhoneCountryCode, string>;

/**
 * Laravel keys a nested rejection by its path, so one bad phone can arrive under
 * any of these. The field shows whichever came back, because it draws the pair
 * as a single thing with a single message.
 */
const PHONE_ERROR_FIELDS = ['phone', 'phone.country_code', 'phone.national_number'] as const;

type AdminTranslate = TFunction<'admin'>;

/** What the name field says under the box, and how it reads. */
type NameVerdict = {
    hint?: string;
    hintTone?: HintTone;
};

/**
 * How much of the address the preview may show.
 *
 * A URL has almost no break opportunities — a dot and a slash give none, only
 * the hyphens between slug words do — so a business named as one long word would
 * be a single token wider than a 320px screen and would push the page sideways.
 * A real address is far shorter than this, so the cap only ever engages on the
 * input that would otherwise break the layout.
 */
const MAX_PREVIEW_CHARACTERS = 34;

/** The booking address, shown without a scheme: it is read, not clicked. */
function bookingUrl(slug: string): string {
    const url = `${window.location.host}/${slug}`;

    return url.length > MAX_PREVIEW_CHARACTERS
        ? `${url.slice(0, MAX_PREVIEW_CHARACTERS)}…`
        : url;
}

/**
 * The server's answer about the name, as a line under the field.
 *
 * Three of the five states say nothing at all. `checking` is silent because a
 * "Checking…" that appears and disappears within half a second is the flicker
 * this whole feature exists to avoid, and `unknown` is silent because a question
 * that never reached the server is not a fault of the name someone just typed —
 * submitting asks again, and that answer is the authoritative one.
 */
function nameVerdict(status: NameStatus, slug: string | null, t: AdminTranslate): NameVerdict {
    if (status === 'taken') {
        return { hint: t('onboarding.name.taken'), hintTone: 'critical' };
    }

    if (status !== 'available') {
        return {};
    }

    // A free name normally comes back with the slug it would take. When it does
    // not, the verdict still stands — it is the preview that has nothing to show.
    return slug === null
        ? { hint: t('onboarding.name.available'), hintTone: 'positive' }
        : { hint: t('onboarding.name.link', { url: bookingUrl(slug) }), hintTone: 'positive' };
}

function optionsStatusFrom(isPending: boolean, isError: boolean): ComboboxOptionsStatus {
    if (isPending) {
        return 'pending';
    }

    if (isError) {
        return 'error';
    }

    return 'ready';
}

function phoneErrorFrom(fieldErrors: FieldErrors): string | undefined {
    return PHONE_ERROR_FIELDS.map((field) => fieldErrors[field]).find(
        (message) => message !== undefined,
    );
}

/**
 * The field hands back whatever its `select` holds, which is a string. Narrowing
 * it here rather than asserting keeps the payload's country honest: a value the
 * catalogue does not offer never becomes one this form claims to have.
 */
function isSupportedCountry(code: string): code is PhoneCountryCode {
    return SUPPORTED_PHONE_COUNTRIES.some((country) => country.code === code);
}

type FormValues = {
    name: string;
    industryId: string | null;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
};

/**
 * The request body, built from what is in the boxes.
 *
 * Nothing is validated on the way out. An industry nobody picked is sent as an
 * empty string rather than withheld, because `CreateBusinessRequest` is the only
 * authority on what is required and a 422 naming the field is a better answer
 * than a button that silently refuses. The phone is the opposite case: it is
 * optional, so a blank number omits the key entirely instead of sending an empty
 * pair for the server to interpret.
 *
 * No slug is ever sent. Deriving one is the server's rule, and the availability
 * check only previews its answer.
 */
function payloadFrom({
    name,
    industryId,
    phoneCountry,
    phoneNumber,
}: FormValues): CreateBusinessPayload {
    const nationalNumber = phoneNumber.trim();

    const payload: CreateBusinessPayload = {
        name: name.trim(),
        timezone: resolvedTimezone(),
        industry_id: industryId ?? '',
    };

    if (nationalNumber === '') {
        return payload;
    }

    return {
        ...payload,
        phone: { country_code: phoneCountry, national_number: nationalNumber },
    };
}

type Props = {
    /**
     * The industry catalogue, already translated and ordered.
     *
     * It arrives as a prop rather than from a hook called here: industries are
     * their own domain, and a domain never reaches sideways into another. The
     * page is where the two meet, which is what a page is for.
     */
    industries: {
        options: readonly ComboboxOption[];
        isPending: boolean;
        isError: boolean;
        refetch: () => void;
    };
};

/**
 * The form that turns an account into a business.
 *
 * It owns the values and the request; the page owns the layout around it. There
 * is no client-side schema: the fields carry native affordances, and every
 * verdict on what is acceptable comes from the server — the name check while
 * typing, the 422 on submit.
 */
export function BusinessOnboardingForm({ industries }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { fieldErrors, formMessage, capture, clearField, reset } = useServerErrors();

    const [name, setName] = useState('');
    const [industryId, setIndustryId] = useState<string | null>(null);
    const [phoneCountry, setPhoneCountry] = useState<PhoneCountryCode>(DEFAULT_PHONE_COUNTRY);
    const [phoneNumber, setPhoneNumber] = useState('');

    const availability = useBusinessNameAvailability(name);
    const createBusiness = useCreateBusiness();

    // A rejected name needs no branch here: `FormField` shows an error instead of
    // a hint, so the server's message replaces the verdict on its own.
    const verdict = nameVerdict(availability.status, availability.slug, t);

    function clearPhoneErrors() {
        for (const field of PHONE_ERROR_FIELDS) {
            clearField(field);
        }
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        reset();

        try {
            await createBusiness.mutateAsync(
                payloadFrom({ name, industryId, phoneCountry, phoneNumber }),
            );

            // Navigation is the component's call; the cache is the hook's.
            router.visit(DASHBOARD_URL);
        } catch (error) {
            capture(error, t('onboarding.errors.unexpected'));
        }
    }

    return (
        <form
            onSubmit={(event) => void submit(event)}
            className="rounded-2xl border border-border bg-card p-6 shadow-xl shadow-foreground/5 sm:p-8 dark:shadow-black/30"
        >
            {formMessage ? (
                <div className="mb-5">
                    <FormAlert message={formMessage} />
                </div>
            ) : null}

            <div className="grid gap-5">
                <FormField
                    id="name"
                    label={t('onboarding.name.label')}
                    // The neutral note is written inside the box rather than under
                    // it: the line underneath belongs to the verdict, and two
                    // things cannot share one line.
                    placeholder={t('onboarding.name.hint')}
                    autoComplete="organization"
                    autoFocus
                    required
                    value={name}
                    onChange={(event) => {
                        setName(event.target.value);
                        clearField('name');
                    }}
                    error={fieldErrors.name}
                    hint={verdict.hint}
                    hintTone={verdict.hintTone}
                    reserveMessageSpace
                />

                <ComboboxField
                    id="industry"
                    label={t('onboarding.industry.label')}
                    placeholder={t('onboarding.industry.placeholder')}
                    options={industries.options}
                    value={industryId}
                    onChange={(value) => {
                        setIndustryId(value);
                        clearField('industry_id');
                    }}
                    optionsStatus={optionsStatusFrom(industries.isPending, industries.isError)}
                    onRetryOptions={industries.refetch}
                    messages={{
                        empty: t('onboarding.industry.empty'),
                        optionsError: t('onboarding.industry.error'),
                        retry: tCommon('actions.tryAgain'),
                        results: industries.isPending
                            ? t('onboarding.industry.loading')
                            : t('onboarding.industry.results', {
                                  count: industries.options.length,
                              }),
                    }}
                    error={fieldErrors.industry_id}
                />

                <PhoneField
                    id="phone"
                    label={t('onboarding.phone.label')}
                    optionalLabel={t('onboarding.phone.optional')}
                    countryLabel={t('onboarding.phone.country')}
                    numberLabel={t('onboarding.phone.number')}
                    countries={SUPPORTED_PHONE_COUNTRIES.map((country) => ({
                        code: country.code,
                        label: `${t(COUNTRY_NAME_KEYS[country.code])} ${country.dialCode}`,
                    }))}
                    country={phoneCountry}
                    onCountryChange={(code) => {
                        if (! isSupportedCountry(code)) {
                            return;
                        }

                        setPhoneCountry(code);
                        clearPhoneErrors();
                    }}
                    number={phoneNumber}
                    onNumberChange={(value) => {
                        setPhoneNumber(value);
                        clearPhoneErrors();
                    }}
                    hint={t('onboarding.phone.hint')}
                    error={phoneErrorFrom(fieldErrors)}
                />

                {/*
                 * A name the check called taken does not disable this button. The
                 * check is an affordance and the server is the authority: a
                 * momentary network failure must not leave someone with a form
                 * they cannot send, and a name the preview doubted may well be
                 * accepted.
                 */}
                <Button
                    type="submit"
                    variant="brand"
                    size="lg"
                    disabled={createBusiness.isPending}
                    className="mt-2 h-12 w-full rounded-xl text-sm"
                >
                    {createBusiness.isPending ? (
                        <>
                            <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                            {t('onboarding.submitting')}
                        </>
                    ) : (
                        t('onboarding.submit')
                    )}
                </Button>
            </div>
        </form>
    );
}
