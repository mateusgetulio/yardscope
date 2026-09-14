import { Head, Link } from '@inertiajs/react';
import type { RequestView, ScopeLineView } from '@/types/scope';

interface Props {
    request: RequestView;
}

const dispositionStyles: Record<ScopeLineView['disposition'], string> = {
    priceable: 'border-green-300 bg-green-50',
    needs_photos: 'border-amber-300 bg-amber-50',
    manual_quote: 'border-stone-300 bg-stone-100',
    suggested: 'border-blue-200 bg-blue-50',
    rejected: 'border-stone-200 bg-white text-stone-500',
};

export default function ResultPage({ request }: Props) {
    return (
        <>
            <Head title="Your job" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <p className="text-sm text-stone-500">
                    <Link href="/">Start over</Link>
                </p>
                <h1 className="mt-2 text-2xl font-semibold">
                    {request.readinessLabel}
                </h1>
                <p className="mt-1 text-stone-600">“{request.sentence}”</p>

                {request.requestNote && (
                    <p className="mt-4 rounded-lg border border-stone-300 bg-stone-100 p-3 text-sm">
                        <span className="font-medium">
                            From the photo analysis:{' '}
                        </span>
                        {request.requestNote}
                    </p>
                )}

                <section className="mt-6 grid gap-2 sm:grid-cols-4">
                    {request.photos.map((photo) => (
                        <img
                            key={photo.number}
                            src={photo.url}
                            alt={`Photo ${photo.number}`}
                            className="aspect-[4/3] w-full rounded-lg object-cover"
                        />
                    ))}
                </section>

                <section className="mt-8 space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide text-stone-500 uppercase">
                        Here is what we found
                    </h2>
                    {request.lines.map((line) => (
                        <article
                            key={line.id}
                            className={`rounded-lg border p-4 ${dispositionStyles[line.disposition]}`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <h3 className="font-semibold">
                                        {line.label}
                                        {line.section
                                            ? `, ${line.section}`
                                            : ''}
                                    </h3>
                                    <p className="text-sm">{line.summary}</p>
                                </div>
                                <div className="text-right text-sm">
                                    <p className="font-medium">
                                        {line.dispositionLabel}
                                    </p>
                                    {line.hours && <p>{line.hours}</p>}
                                    {line.labor && <p>{line.labor} labor</p>}
                                </div>
                            </div>
                            {line.note && (
                                <p className="mt-2 text-sm">{line.note}</p>
                            )}
                            {line.photoRequest && (
                                <p className="mt-2 text-sm font-medium">
                                    {line.photoRequest}
                                </p>
                            )}
                            <p className="mt-2 text-xs text-stone-500">
                                {line.checksPassed} of {line.checksTotal} checks
                                passed
                                {line.evidence.length > 0 &&
                                    `, seen in photo ${line.evidence.map((item) => item.photo).join(', ')}`}
                            </p>
                        </article>
                    ))}
                </section>

                {request.rejected.length > 0 && (
                    <p className="mt-4 text-sm text-stone-500">
                        Left out because the analysis could not be read:{' '}
                        {request.rejected
                            .map((line) => `${line.type} (${line.reason})`)
                            .join('; ')}
                    </p>
                )}

                {request.access.narrowGatePossible && (
                    <p className="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                        Your side gate may be narrow. Is it at least 36 inches
                        wide? Your pro will confirm before bringing equipment.
                    </p>
                )}

                <section className="mt-8 rounded-lg border border-stone-300 bg-white p-4">
                    {request.estimate ? (
                        <>
                            <p className="text-3xl font-semibold">
                                {request.estimate.price}
                            </p>
                            <p className="text-sm text-stone-600">
                                Estimated work {request.estimate.hours},
                                including a {request.estimate.visitFee} visit
                                fee. Synthetic demo rates.
                            </p>
                        </>
                    ) : (
                        <p className="text-stone-600">No price yet.</p>
                    )}
                    {request.excludedSummary && (
                        <p className="mt-2 text-sm text-stone-600">
                            {request.excludedSummary}
                        </p>
                    )}
                    <button
                        type="button"
                        disabled={!request.cta.enabled}
                        className="mt-4 rounded-lg bg-blue-700 px-5 py-2 font-medium text-white disabled:bg-stone-300 disabled:text-stone-600"
                    >
                        {request.cta.label}
                    </button>
                </section>
            </main>
        </>
    );
}
