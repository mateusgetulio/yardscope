import { Head, Link } from '@inertiajs/react';
import type { RequestView } from '@/types/scope';

interface Props {
    request: RequestView;
}

export default function BookedPage({ request }: Props) {
    const priced = request.lines.filter(
        (line) => line.disposition === 'priceable',
    );

    return (
        <>
            <Head title="Booked" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-2xl font-semibold">
                    Booked at {request.bookedPrice}
                </h1>
                <p className="mt-2 text-stone-600">
                    A pro gets a brief built from exactly what you saw on the
                    previous page, with every value marked as seen in the photos
                    or corrected by you.
                </p>

                <section className="mt-6 rounded-lg border border-stone-300 bg-white p-4">
                    <h2 className="text-sm font-semibold tracking-wide text-stone-500 uppercase">
                        Priced work
                    </h2>
                    <ul className="mt-2 space-y-1 text-sm">
                        {priced.map((line) => (
                            <li key={line.id}>
                                {line.label}
                                {line.section ? `, ${line.section}` : ''}:{' '}
                                {line.summary.toLowerCase()}
                                {line.hours ? `, ${line.hours}` : ''}
                            </li>
                        ))}
                    </ul>
                    {request.excludedSummary && (
                        <p className="mt-3 text-sm text-stone-600">
                            {request.excludedSummary}
                        </p>
                    )}
                    {request.access.narrowGatePossible && (
                        <p className="mt-3 text-sm text-stone-600">
                            Your pro will confirm the side gate width before
                            bringing equipment.
                        </p>
                    )}
                </section>

                <p className="mt-6 text-sm text-stone-500">
                    <Link
                        href={`/requests/${request.id}`}
                        className="underline"
                    >
                        Back to the job
                    </Link>
                </p>
            </main>
        </>
    );
}
