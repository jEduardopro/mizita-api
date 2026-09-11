import { Link, usePage } from '@inertiajs/react';
import { AgendaPreview } from '@/components/AgendaPreview';
import { Button } from '@/components/ui/button';
import { PublicLayout } from '@/layouts/PublicLayout';

/**
 * The public landing page, rendered by `Inertia::render('public/welcome')`.
 */

/**
 * The product's own vocabulary, defined once. These are the three words a new
 * owner has to learn before the dashboard makes sense.
 */
const glossary = [
    {
        term: 'Slot',
        definition:
            'A start time we have already checked against opening hours, time off and everything booked.',
    },
    {
        term: 'Block',
        definition:
            'What a booking really consumes: the buffer before, the service itself, the buffer after.',
    },
    {
        term: 'Horizon',
        definition:
            'How far ahead your customers may book. You set it, and the lead time, per business.',
    },
];

export default function Welcome() {
    const { name, auth } = usePage().props;

    return (
        <PublicLayout title="Appointment scheduling for small teams">
            <section className="mx-auto w-full max-w-5xl px-5 py-14 sm:px-8 sm:py-20">
                <div className="grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
                    <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500">
                        <p className="text-[0.6875rem] font-medium tracking-[0.18em] text-muted-foreground uppercase">
                            Appointment scheduling
                        </p>

                        <h1 className="mt-5 font-heading text-[clamp(2.5rem,7vw,4rem)] leading-[0.98] font-medium tracking-[-0.045em] text-balance">
                            Bookings that respect the working day.
                        </h1>

                        <p className="mt-5 max-w-md text-base leading-relaxed text-muted-foreground">
                            {name} holds your opening hours, your staff, your services and your
                            time off in one place, so every slot you offer is one you can
                            actually keep.
                        </p>

                        <div className="mt-8 flex flex-wrap items-center gap-2">
                            {auth.isAuthenticated ? (
                                <Button asChild size="lg">
                                    <Link href="/dashboard">Go to dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild size="lg">
                                        <Link href="/register">Create account</Link>
                                    </Button>
                                    <Button asChild variant="outline" size="lg">
                                        <Link href="/login">Log in</Link>
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                        <AgendaPreview />
                    </div>
                </div>
            </section>

            <section className="mx-auto w-full max-w-5xl px-5 pb-20 sm:px-8">
                <dl className="grid gap-px overflow-hidden rounded-xl bg-border sm:grid-cols-3">
                    {glossary.map((entry) => (
                        <div key={entry.term} className="bg-background p-5">
                            <dt className="font-heading text-sm font-medium tracking-[-0.01em]">
                                {entry.term}
                            </dt>
                            <dd className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                {entry.definition}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>
        </PublicLayout>
    );
}
