import { Head, Link } from '@inertiajs/react';
import type { EvalResults } from '@/types/scope';

interface Props {
    results: EvalResults | null;
    file: string | null;
}

const metricLabels: Record<string, string> = {
    sets: 'Labeled sets',
    schema_valid_rate: 'Schema-valid answers',
    service_precision: 'Service precision',
    service_recall: 'Service recall',
    count_exact_rate: 'Counts exact',
    count_within_one_rate: 'Counts within one',
    hallucinated_lines: 'Hallucinated lines',
    disposition_accuracy: 'Dispositions correct',
    readiness_accuracy: 'Request readiness correct',
    photo_request_accuracy: 'Photo requests correct',
    counting_photo_accuracy: 'Counting photo correct',
    unusable_photo_accuracy: 'Unusable photos flagged',
};

function format(name: string, value: number | null): string {
    if (value === null) {
        return 'nothing to measure';
    }
    if (name === 'sets' || name === 'hallucinated_lines') {
        return String(value);
    }
    return `${Math.round(value * 100)}%`;
}

export default function EvalsPage({ results, file }: Props) {
    return (
        <>
            <Head title="Evals" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <p className="text-sm text-stone-500">
                    <Link href="/">Request form</Link>
                </p>
                <h1 className="mt-2 text-2xl font-semibold">Latest eval run</h1>
                {results === null ? (
                    <p className="mt-2 text-stone-600">
                        No run recorded yet. Run{' '}
                        <code>php artisan yardscope:eval --live</code> or{' '}
                        <code>--fixtures</code>.
                    </p>
                ) : (
                    <>
                        <p className="mt-2 text-stone-600">
                            {results.mode === 'live'
                                ? `Live run through the ${results.driver} driver`
                                : 'Replay of recorded answers'}{' '}
                            on {new Date(results.ran_at).toLocaleString()} (
                            {file}). Numbers are exactly what the run produced.
                        </p>
                        <dl className="mt-6 grid gap-2 sm:grid-cols-2">
                            {Object.entries(results.metrics).map(
                                ([name, value]) => (
                                    <div
                                        key={name}
                                        className="flex justify-between rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm"
                                    >
                                        <dt>{metricLabels[name] ?? name}</dt>
                                        <dd className="font-medium">
                                            {format(name, value)}
                                        </dd>
                                    </div>
                                ),
                            )}
                        </dl>
                        <section className="mt-8 space-y-3">
                            {results.sets.map((set) => (
                                <article
                                    key={set.slug}
                                    className={`rounded-lg border p-4 text-sm ${set.schema_valid ? 'border-stone-300 bg-white' : 'border-red-300 bg-red-50'}`}
                                >
                                    <h2 className="font-semibold">
                                        {set.slug}{' '}
                                        <span className="font-normal text-stone-500">
                                            {set.scenario.replaceAll('_', ' ')}
                                        </span>
                                    </h2>
                                    {set.failure ? (
                                        <p className="mt-1">{set.failure}</p>
                                    ) : (
                                        <ul className="mt-1 space-y-1">
                                            <li>
                                                Readiness:{' '}
                                                {set.observed_readiness}
                                                {set.readiness_correct === false
                                                    ? ` (expected ${set.expected_readiness})`
                                                    : ''}
                                            </li>
                                            <li>
                                                Services:{' '}
                                                {set.observed_services.join(
                                                    ', ',
                                                ) || 'none'}
                                                {set.hallucinated.length > 0
                                                    ? `; hallucinated ${set.hallucinated.join(', ')}`
                                                    : ''}
                                            </li>
                                            {set.counts.map((count) => (
                                                <li key={count.service}>
                                                    {count.service}: counted{' '}
                                                    {count.observed ??
                                                        'nothing'}{' '}
                                                    for {count.expected}
                                                    {count.exact ? '' : ', off'}
                                                </li>
                                            ))}
                                            {set.dispositions
                                                .filter((line) => !line.correct)
                                                .map((line) => (
                                                    <li key={line.service}>
                                                        {line.service}:{' '}
                                                        {line.observed ??
                                                            'missing'}{' '}
                                                        instead of{' '}
                                                        {line.expected}
                                                    </li>
                                                ))}
                                            {set.photo_request_correct ===
                                                false && (
                                                <li>
                                                    Photo request did not match.
                                                </li>
                                            )}
                                            {set.unusable_photos_correct ===
                                                false && (
                                                <li>
                                                    Unusable photos were not
                                                    flagged as labeled.
                                                </li>
                                            )}
                                        </ul>
                                    )}
                                </article>
                            ))}
                        </section>
                    </>
                )}
            </main>
        </>
    );
}
