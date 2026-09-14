import { useTranslation } from "react-i18next";
import { LegalBody } from "@/components/public/legal/LegalBody";
import { useLegalDocument } from "@/components/public/legal/use-legal-document";
import { legalUpdatedOn, type LegalDocumentName } from "@/content/legal/entity";

/**
 * The parts are built into a local date on purpose: `new Date('2026-09-12')` is
 * parsed as UTC midnight, which in Mexico City is the evening of the 11th.
 */
function formatRevisionDate(isoDate: string, language: string): string {
    const [year, month, day] = isoDate.split("-").map(Number);

    return new Intl.DateTimeFormat(language, {
        day: "numeric",
        month: "long",
        year: "numeric",
    }).format(new Date(year, month - 1, day));
}

type Props = {
    document: LegalDocumentName;
};

export function LegalDocument({ document }: Props) {
    const { t, i18n } = useTranslation("common");

    const state = useLegalDocument(document, i18n.language);
    const revisedOn = legalUpdatedOn[document];

    return (
        <article className="mx-auto w-full max-w-4xl px-5 py-12 sm:px-8 sm:py-16">
            <header className="border-b border-border pb-8">
                <h1 className="font-heading text-[clamp(1.75rem,4.6vw,2.75rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance">
                    {state.status === "ready" ? (
                        state.content.title
                    ) : (
                        <span
                            aria-hidden="true"
                            className="inline-block h-[0.85em] w-full max-w-md animate-pulse rounded-lg bg-muted align-middle motion-reduce:animate-none"
                        />
                    )}
                </h1>

                <p className="mt-5 flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                    <span
                        aria-hidden="true"
                        className="size-1.5 rounded-[2px] bg-primary"
                    />
                    <span className="text-[0.6875rem] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                        {t("legal.lastUpdated")}
                    </span>
                    <time
                        dateTime={revisedOn}
                        className="text-xs text-foreground"
                    >
                        {formatRevisionDate(revisedOn, i18n.language)}
                    </time>
                </p>
            </header>

            <div className="mt-10 sm:mt-12">
                <LegalBody state={state} />
            </div>
        </article>
    );
}
