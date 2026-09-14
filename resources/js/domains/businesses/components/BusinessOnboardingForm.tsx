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

const DASHBOARD_URL = '/dashboard';

const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

const COUNTRY_NAME_KEYS = {
    MX: 'onboarding.phone.countries.MX',
    US: 'onboarding.phone.countries.US',
} as const satisfies Record<PhoneCountryCode, string>;

const PHONE_ERROR_FIELDS = ['phone', 'phone.country_code', 'phone.national_number'] as const;

type AdminTranslate = TFunction<'admin'>;

type NameVerdict = {
    hint?: string;
    hintTone?: HintTone;
};

const MAX_PREVIEW_CHARACTERS = 34;

function bookingUrl(slug: string): string {
    const url = `${window.location.host}/${slug}`;

    return url.length > MAX_PREVIEW_CHARACTERS
        ? `${url.slice(0, MAX_PREVIEW_CHARACTERS)}…`
        : url;
}

function nameVerdict(status: NameStatus, slug: string | null, t: AdminTranslate): NameVerdict {
    if (status === 'taken') {
        return { hint: t('onboarding.name.taken'), hintTone: 'critical' };
    }

    if (status !== 'available') {
        return {};
    }

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

function isSupportedCountry(code: string): code is PhoneCountryCode {
    return SUPPORTED_PHONE_COUNTRIES.some((country) => country.code === code);
}

type FormValues = {
    name: string;
    industryId: string | null;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
};

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
    industries: {
        options: readonly ComboboxOption[];
        isPending: boolean;
        isError: boolean;
        refetch: () => void;
    };
};

export function BusinessOnboardingForm({ industries }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const [name, setName] = useState('');
    const [industryId, setIndustryId] = useState<string | null>(null);
    const [phoneCountry, setPhoneCountry] = useState<PhoneCountryCode>(DEFAULT_PHONE_COUNTRY);
    const [phoneNumber, setPhoneNumber] = useState('');

    const availability = useBusinessNameAvailability(name);
    const createBusiness = useCreateBusiness();

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
            <div className="grid gap-5">
                <FormField
                    id="name"
                    label={t('onboarding.name.label')}
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
