import { Link } from '@inertiajs/react';
import Layout from '@/components/layout';
import { buttonPrimary, buttonSecondary, cardClass } from '@/lib/styles';
import type { RequestView } from '@/types/scope';

interface Props {
    request: RequestView;
}

export default function BookedPage({ request }: Props) {
    const priced = request.lines.filter(
        (line) => line.disposition === 'priceable',
    );

    return (
        <Layout title="Booked">
            <div className={`p-6 sm:p-8 ${cardClass}`}>
                <div className="flex items-start gap-4">
                    <span
                        aria-hidden="true"
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 text-lg font-semibold text-green-700"
                    >
                        ✓
                    </span>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Booked at {request.bookedPrice}
                        </h1>
                        <p className="mt-1 text-sm text-stone-600">
                            A pro gets a brief built from exactly what you saw,
                            with every value marked as seen in the photos or
                            corrected by you. Synthetic demo rates.
                        </p>
                    </div>
                </div>

                <dl className="mt-6 divide-y divide-stone-100 border-y border-stone-100">
                    {priced.map((line) => (
                        <div
                            key={line.id}
                            className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-3 text-sm"
                        >
                            <dt>
                                <span className="font-medium">
                                    {line.label}
                                    {line.section ? `, ${line.section}` : ''}
                                </span>
                                <span className="ml-2 text-stone-500">
                                    {line.summary.toLowerCase()}
                                </span>
                            </dt>
                            <dd className="text-stone-600 tabular-nums">
                                {line.hours}
                            </dd>
                        </div>
                    ))}
                </dl>

                {(request.excludedSummary ||
                    request.access.narrowGatePossible) && (
                    <div className="mt-4 space-y-1 text-sm text-stone-600">
                        {request.excludedSummary && (
                            <p>{request.excludedSummary}</p>
                        )}
                        {request.access.narrowGatePossible && (
                            <p>
                                Your pro will confirm the side gate width before
                                bringing equipment.
                            </p>
                        )}
                    </div>
                )}

                <div className="mt-6 flex flex-wrap gap-2">
                    <Link
                        href={`/requests/${request.id}/pro`}
                        className={buttonPrimary}
                    >
                        See what the pro sees
                    </Link>
                    <Link
                        href={`/requests/${request.id}`}
                        className={`${buttonSecondary} py-2`}
                    >
                        Back to the job
                    </Link>
                </div>
            </div>
        </Layout>
    );
}
