import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PipelinePanel from '@/components/pipeline-panel';
import type { BriefView, PipelineView, ProActionView } from '@/types/scope';

interface Props {
    brief: BriefView;
    pipeline: PipelineView;
}

const actionLabels: Record<ProActionView['kind'], string> = {
    accept_scope: 'Accepted the scope',
    request_photo: 'Asked for a photo',
    adjust_quote: 'Adjusted the quote',
};

export default function ProPage({ brief, pipeline }: Props) {
    return (
        <>
            <Head title="Pre-visit brief" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <p className="text-sm text-stone-500">
                    <Link href={`/requests/${brief.id}`}>Customer view</Link>
                </p>
                <h1 className="mt-2 text-2xl font-semibold">Pre-visit brief</h1>
                <p className="mt-1 text-stone-600">
                    {brief.readinessLabel}
                    {brief.booked ? `, booked at ${brief.bookedPrice}` : ''}.
                    Customer said: “{brief.sentence}”
                </p>

                <section className="mt-6 space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide text-stone-500 uppercase">
                        Scope
                    </h2>
                    {brief.lines.map((line) => (
                        <article
                            key={line.id}
                            className={`rounded-lg border p-4 ${line.removedByCustomer || line.disposition === 'rejected' ? 'border-stone-200 text-stone-500' : 'border-stone-300 bg-white'}`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h3 className="font-semibold">
                                    {line.label}
                                    {line.section ? `, ${line.section}` : ''}
                                </h3>
                                <span className="text-sm">
                                    {line.dispositionLabel}
                                </span>
                            </div>
                            <p className="mt-1">
                                {line.summary}{' '}
                                <span
                                    className={`rounded px-1.5 py-0.5 text-xs ${line.origin === 'customer_corrected' ? 'bg-amber-100 text-amber-900' : 'bg-stone-100 text-stone-700'}`}
                                >
                                    {line.originLabel}
                                </span>
                            </p>
                            {line.observedSummary && (
                                <p className="text-sm text-stone-600">
                                    Seen in the photos: {line.observedSummary}
                                    {line.customerReason
                                        ? `. Customer: “${line.customerReason}”`
                                        : ''}
                                </p>
                            )}
                            {line.removedByCustomer && (
                                <p className="text-sm">
                                    Removed by the customer
                                    {line.customerReason
                                        ? `: “${line.customerReason}”`
                                        : ''}
                                </p>
                            )}
                            {line.note && !line.removedByCustomer && (
                                <p className="text-sm">{line.note}</p>
                            )}
                            {line.evidence.length > 0 && (
                                <ul className="mt-1 text-xs text-stone-500">
                                    {line.evidence.map((item, index) => (
                                        <li key={index}>
                                            Photo {item.photo}: {item.note}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </article>
                    ))}
                </section>

                <section className="mt-6">
                    <h2 className="text-sm font-semibold tracking-wide text-stone-500 uppercase">
                        Photos
                    </h2>
                    <div className="mt-2 grid gap-3 sm:grid-cols-3">
                        {brief.photos.map((photo) => (
                            <figure key={photo.number}>
                                <img
                                    src={photo.url}
                                    alt={`Photo ${photo.number}`}
                                    className="aspect-[4/3] w-full rounded-lg object-cover"
                                />
                                <figcaption className="mt-1 text-xs text-stone-600">
                                    Photo {photo.number}
                                    {photo.notes.length === 0
                                        ? ': nothing noted'
                                        : ''}
                                    <ul>
                                        {photo.notes.map((note, index) => (
                                            <li key={index}>{note}</li>
                                        ))}
                                    </ul>
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                </section>

                {(brief.accessNotes.length > 0 ||
                    brief.openQuestions.length > 0) && (
                    <section className="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
                        {brief.accessNotes.map((note) => (
                            <p key={note}>{note}</p>
                        ))}
                        {brief.openQuestions.length > 0 && (
                            <>
                                <h2 className="mt-2 font-semibold">
                                    Open questions
                                </h2>
                                <ul className="list-disc pl-5">
                                    {brief.openQuestions.map((question) => (
                                        <li key={question}>{question}</li>
                                    ))}
                                </ul>
                            </>
                        )}
                    </section>
                )}

                <section className="mt-6 rounded-lg border border-stone-300 bg-white p-4">
                    <p className="text-lg font-semibold">
                        {brief.estimate
                            ? `${brief.estimate.price}, ${brief.estimate.hours}`
                            : 'No price yet'}
                    </p>
                    <ProActions requestId={brief.id} />
                    {brief.actions.length > 0 && (
                        <ul className="mt-4 space-y-1 text-sm text-stone-600">
                            {brief.actions.map((action) => (
                                <li key={action.id}>
                                    {actionLabels[action.kind]}
                                    {action.adjustedPrice
                                        ? ` to ${action.adjustedPrice}`
                                        : ''}
                                    {action.reason ? `: ${action.reason}` : ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <PipelinePanel pipeline={pipeline} />
            </main>
        </>
    );
}

function ProActions({ requestId }: { requestId: string }) {
    const [kind, setKind] = useState<ProActionView['kind'] | null>(null);
    const form = useForm({ kind: '', reason: '', adjusted_price: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, kind: kind ?? '' }));
        form.post(`/requests/${requestId}/pro/actions`, {
            onSuccess: () => {
                setKind(null);
                form.reset();
            },
        });
    }

    return (
        <div className="mt-3">
            <div className="flex flex-wrap gap-2 text-sm">
                <button
                    type="button"
                    disabled={form.processing}
                    onClick={() => {
                        setKind('accept_scope');
                        form.transform(() => ({
                            kind: 'accept_scope',
                            reason: '',
                            adjusted_price: '',
                        }));
                        form.post(`/requests/${requestId}/pro/actions`);
                    }}
                    className="rounded bg-blue-700 px-3 py-1 font-medium text-white disabled:opacity-60"
                >
                    Accept scope
                </button>
                <button
                    type="button"
                    onClick={() =>
                        setKind(
                            kind === 'request_photo' ? null : 'request_photo',
                        )
                    }
                    className="rounded border border-stone-400 bg-white px-3 py-1"
                >
                    Request photo
                </button>
                <button
                    type="button"
                    onClick={() =>
                        setKind(kind === 'adjust_quote' ? null : 'adjust_quote')
                    }
                    className="rounded border border-stone-400 bg-white px-3 py-1"
                >
                    Adjust quote
                </button>
            </div>
            {(kind === 'request_photo' || kind === 'adjust_quote') && (
                <form
                    onSubmit={submit}
                    className="mt-3 grid gap-3 text-sm sm:grid-cols-3"
                >
                    {kind === 'adjust_quote' && (
                        <label className="block">
                            <span className="text-xs text-stone-500">
                                Adjusted price, $
                            </span>
                            <input
                                type="number"
                                min={0}
                                step="1"
                                value={form.data.adjusted_price}
                                onChange={(event) =>
                                    form.setData(
                                        'adjusted_price',
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full rounded border border-stone-300 px-2 py-1"
                            />
                            {form.errors.adjusted_price && (
                                <span className="text-red-700">
                                    {form.errors.adjusted_price}
                                </span>
                            )}
                        </label>
                    )}
                    <label className="block sm:col-span-2">
                        <span className="text-xs text-stone-500">
                            {kind === 'request_photo'
                                ? 'What should the customer photograph?'
                                : 'Why'}
                        </span>
                        <input
                            type="text"
                            maxLength={200}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            className="mt-1 w-full rounded border border-stone-300 px-2 py-1"
                        />
                        {form.errors.reason && (
                            <span className="text-red-700">
                                {form.errors.reason}
                            </span>
                        )}
                    </label>
                    <div className="sm:col-span-3">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded bg-blue-700 px-4 py-1 font-medium text-white disabled:opacity-60"
                        >
                            {kind === 'request_photo'
                                ? 'Send photo request'
                                : 'Save adjustment'}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
