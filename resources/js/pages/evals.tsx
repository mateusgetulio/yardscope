import Layout from '@/components/layout';
import { Badge, SectionTitle } from '@/components/ui';
import { cardClass } from '@/lib/styles';
import type { EvalResults } from '@/types/scope';

interface Props {
    results: EvalResults | null;
    file: string | null;
}

type EvalSet = EvalResults['sets'][number];

const metricLabels: Record<string, string> = {
    sets: 'Labeled sets',
    schema_valid_rate: 'Schema-valid answers',
    service_precision: 'Service precision',
    service_recall: 'Service recall',
    count_exact_rate: 'Counts exact',
    count_within_one_rate: 'Counts within one',
    hallucinated_lines: 'Hallucinated lines',
    duplicate_lines: 'Duplicate lines',
    severity_accuracy: 'Severity correct',
    size_accuracy: 'Size correct',
    disposition_accuracy: 'Dispositions correct',
    readiness_accuracy: 'Request readiness correct',
    photo_request_accuracy: 'Photo requests correct',
    counting_photo_accuracy: 'Counting photo correct',
    unusable_photo_accuracy: 'Unusable photos flagged',
};

const counts = new Set(['sets', 'hallucinated_lines', 'duplicate_lines']);

function format(name: string, value: number | null): string {
    if (value === null) {
        return 'n/a';
    }
    return counts.has(name) ? String(value) : `${Math.round(value * 100)}%`;
}

/** "yard_cleanup@backyard" reads as "Yard cleanup, backyard". */
function service(key: string): string {
    const [type, section] = key.split('@');
    const label = type.replaceAll('_', ' ');
    const named = label.charAt(0).toUpperCase() + label.slice(1);

    return section === 'none'
        ? `${named} (no section)`
        : `${named}, ${section.replaceAll('_', ' ')}`;
}

/** Disposition and readiness codes in the words the result page uses. */
function words(code: string | null): string {
    const named: Record<string, string> = {
        priceable: 'priced',
        needs_photos: 'needs photos',
        manual_quote: 'pro quote',
        suggested: 'suggested',
        rejected: 'rejected',
        ready: 'ready',
        partial: 'partial',
    };

    return code === null
        ? 'nothing'
        : (named[code] ?? code.replaceAll('_', ' '));
}

function misses(set: EvalSet): string[] {
    if (!set.schema_valid) {
        return [set.failure ?? 'No readable answer.'];
    }

    return [
        ...(set.readiness_correct === false
            ? [
                  `Readiness ${words(set.observed_readiness)}, label says ${words(set.expected_readiness)}`,
              ]
            : []),
        ...set.hallucinated.map((key) => `Hallucinated ${service(key)}`),
        ...set.counts
            .filter((count) => !count.exact)
            .map(
                (count) =>
                    `${service(count.service)}: counted ${count.observed ?? 'nothing'}, label says ${count.expected}`,
            ),
        ...set.attributes
            .filter((item) => !item.correct)
            .map(
                (item) =>
                    `${service(item.service)}: ${item.attribute} ${item.observed ?? 'missing'}, label says ${item.expected}`,
            ),
        ...set.counting_photos
            .filter((item) => !item.correct)
            .map(
                (item) =>
                    `${service(item.service)}: counted from photo ${item.observed ?? 'none'}, label says ${item.expected}`,
            ),
        ...set.dispositions
            .filter((line) => !line.correct)
            .map(
                (line) =>
                    `${service(line.service)}: ${line.observed === null ? 'no line' : words(line.observed)}, label says ${words(line.expected)}`,
            ),
        ...(set.duplicate_lines > 0
            ? [
                  `${set.duplicate_lines} duplicate line${set.duplicate_lines === 1 ? '' : 's'}`,
              ]
            : []),
        ...(set.photo_request_correct === false
            ? ['Photo request did not match the label']
            : []),
        ...(set.unusable_photos_correct === false
            ? ['Unusable photos not flagged as labeled']
            : []),
    ];
}

export default function EvalsPage({ results, file }: Props) {
    return (
        <Layout title="Evals">
            <h1 className="text-3xl font-semibold tracking-tight">
                Latest eval run
            </h1>
            {results === null ? (
                <p className="mt-3 text-stone-600">
                    No run recorded yet. Run{' '}
                    <code className="rounded bg-stone-100 px-1">
                        php artisan yardscope:eval --live
                    </code>{' '}
                    or{' '}
                    <code className="rounded bg-stone-100 px-1">
                        --fixtures
                    </code>
                    .
                </p>
            ) : (
                <>
                    <p className="mt-3 text-stone-600">
                        {results.mode === 'live'
                            ? `Live run through the ${results.driver} driver`
                            : 'Replay of recorded answers'}{' '}
                        on {new Date(results.ran_at).toLocaleString()}. Numbers
                        are exactly what the run produced.
                    </p>
                    <p className="mt-1 font-mono text-xs text-stone-400">
                        evals/results/{file}
                    </p>

                    <dl className="mt-8 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {Object.entries(results.metrics).map(
                            ([name, value]) => (
                                <div
                                    key={name}
                                    className={`px-4 py-3 ${cardClass}`}
                                >
                                    <dd className="text-2xl font-semibold tabular-nums">
                                        {format(name, value)}
                                    </dd>
                                    <dt className="mt-0.5 text-xs text-stone-500">
                                        {metricLabels[name] ?? name}
                                    </dt>
                                </div>
                            ),
                        )}
                    </dl>

                    <section className="mt-10 space-y-3">
                        <SectionTitle>Per set</SectionTitle>
                        {results.sets.map((set) => {
                            const found = misses(set);

                            return (
                                <article
                                    key={set.slug}
                                    className={`p-4 ${cardClass}`}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h2 className="font-medium">
                                            {set.slug}
                                        </h2>
                                        <div className="flex gap-1.5">
                                            <Badge tone="stone">
                                                {set.scenario.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                            {found.length === 0 ? (
                                                <Badge tone="green">
                                                    matches labels
                                                </Badge>
                                            ) : (
                                                <Badge tone="amber">
                                                    {found.length}{' '}
                                                    {found.length === 1
                                                        ? 'miss'
                                                        : 'misses'}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    {set.schema_valid && (
                                        <p className="mt-2 text-sm text-stone-600">
                                            {words(set.observed_readiness)}
                                            {', '}
                                            {set.observed_services.length > 0
                                                ? set.observed_services
                                                      .map(service)
                                                      .join('; ')
                                                : 'no service lines from the model'}
                                        </p>
                                    )}
                                    {found.length > 0 && (
                                        <ul className="mt-2 space-y-0.5 text-sm text-amber-900">
                                            {found.map((miss) => (
                                                <li
                                                    key={miss}
                                                    className="flex gap-2"
                                                >
                                                    <span aria-hidden="true">
                                                        ✗
                                                    </span>
                                                    <span>{miss}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </article>
                            );
                        })}
                    </section>
                </>
            )}
        </Layout>
    );
}
