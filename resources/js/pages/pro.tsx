import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '@/components/layout';
import PipelinePanel from '@/components/pipeline-panel';
import { Badge, Notice, PhotoNumber, SectionTitle } from '@/components/ui';
import {
    buttonPrimary,
    buttonSecondary,
    cardClass,
    dispositionTone,
    inputClass,
} from '@/lib/styles';
import type { BriefView, PipelineView, ProActionKind } from '@/types/scope';

interface Props {
    brief: BriefView;
    pipeline: PipelineView;
}

export default function ProPage({ brief, pipeline }: Props) {
    return (
        <Layout title="Pre-visit brief">
            <div className="flex flex-wrap items-center gap-2">
                <Badge tone="blue">For the pro</Badge>
                <Badge tone="stone">{brief.readinessLabel}</Badge>
                {brief.booked && (
                    <Badge tone="green">Booked at {brief.bookedPrice}</Badge>
                )}
            </div>
            <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                Pre-visit brief
            </h1>
            <blockquote className="mt-3 border-l-2 border-stone-300 pl-3 text-stone-600">
                “{brief.sentence}”
            </blockquote>
            <p className="mt-2 text-sm text-stone-500">
                <Link
                    href={`/requests/${brief.id}`}
                    className="underline decoration-stone-300 underline-offset-2 hover:text-stone-800"
                >
                    Open the customer view
                </Link>
            </p>

            <section className="mt-8 space-y-3">
                <SectionTitle>Scope</SectionTitle>
                {brief.lines.map((line) => {
                    const out =
                        line.removedByCustomer ||
                        line.disposition === 'rejected';

                    return (
                        <article
                            key={line.id}
                            className={`p-4 ${cardClass} ${out ? 'text-stone-500' : ''}`}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 className="font-semibold">
                                        {line.label}
                                        {line.section
                                            ? `, ${line.section}`
                                            : ''}
                                    </h3>
                                    <p className="mt-0.5 flex flex-wrap items-center gap-2">
                                        <span>{line.summary}</span>
                                        <Badge
                                            tone={
                                                line.origin ===
                                                'customer_corrected'
                                                    ? 'amber'
                                                    : 'stone'
                                            }
                                        >
                                            {line.originLabel}
                                        </Badge>
                                    </p>
                                </div>
                                <Badge tone={dispositionTone[line.disposition]}>
                                    {line.dispositionLabel}
                                </Badge>
                            </div>
                            {line.observedSummary && (
                                <p className="mt-2 text-sm text-stone-600">
                                    Seen in the photos: {line.observedSummary}
                                    {line.customerReason
                                        ? `. Customer: “${line.customerReason}”`
                                        : ''}
                                </p>
                            )}
                            {line.removedByCustomer && (
                                <p className="mt-2 text-sm">
                                    Removed by the customer
                                    {line.customerReason
                                        ? `: “${line.customerReason}”`
                                        : ''}
                                </p>
                            )}
                            {line.note && !line.removedByCustomer && (
                                <p className="mt-2 text-sm">{line.note}</p>
                            )}
                            {line.evidence.length > 0 && (
                                <ul className="mt-3 space-y-1 border-t border-stone-100 pt-3 text-xs text-stone-500">
                                    {line.evidence.map((item, index) => (
                                        <li key={index} className="flex gap-2">
                                            <span className="shrink-0 font-medium text-stone-600">
                                                Photo {item.photo}
                                            </span>
                                            <span>{item.note}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </article>
                    );
                })}
            </section>

            {(brief.accessNotes.length > 0 ||
                brief.openQuestions.length > 0) && (
                <section className="mt-8">
                    <Notice tone="amber">
                        {brief.accessNotes.map((note) => (
                            <p key={note} className="font-medium">
                                {note}
                            </p>
                        ))}
                        {brief.openQuestions.length > 0 && (
                            <>
                                <h2
                                    className={`font-semibold ${brief.accessNotes.length > 0 ? 'mt-3' : ''}`}
                                >
                                    Open questions
                                </h2>
                                <ul className="mt-1 list-disc space-y-1 pl-5">
                                    {brief.openQuestions.map((question) => (
                                        <li key={question}>{question}</li>
                                    ))}
                                </ul>
                            </>
                        )}
                    </Notice>
                </section>
            )}

            <section className="mt-8 space-y-3">
                <SectionTitle>Photos</SectionTitle>
                <div className="grid gap-4 sm:grid-cols-2">
                    {brief.photos.map((photo) => (
                        <figure
                            key={photo.number}
                            className={`overflow-hidden ${cardClass}`}
                        >
                            <a
                                href={photo.url}
                                target="_blank"
                                rel="noreferrer"
                                className="relative block"
                            >
                                <img
                                    src={photo.url}
                                    alt={`Photo ${photo.number}`}
                                    className="aspect-[4/3] w-full object-cover"
                                />
                                <PhotoNumber number={photo.number} />
                            </a>
                            <figcaption className="px-3 py-2 text-xs text-stone-600">
                                {photo.notes.length === 0 ? (
                                    <span className="text-stone-400">
                                        Nothing noted on this photo.
                                    </span>
                                ) : (
                                    <ul className="space-y-1">
                                        {photo.notes.map((note, index) => (
                                            <li key={index}>{note}</li>
                                        ))}
                                    </ul>
                                )}
                            </figcaption>
                        </figure>
                    ))}
                </div>
            </section>

            <section className={`mt-8 p-5 ${cardClass}`}>
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <p className="text-xs text-stone-500">
                            Current estimate
                        </p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {brief.estimate
                                ? brief.estimate.price
                                : 'No price yet'}
                        </p>
                    </div>
                    {brief.estimate && (
                        <p className="text-sm text-stone-600">
                            {brief.estimate.hours} of estimated work
                        </p>
                    )}
                </div>
                <ProActions requestId={brief.id} />
                {brief.actions.length > 0 && (
                    <ul className="mt-5 space-y-1.5 border-t border-stone-100 pt-4 text-sm text-stone-600">
                        {brief.actions.map((action) => (
                            <li
                                key={action.id}
                                className="flex flex-wrap gap-2"
                            >
                                <Badge
                                    tone={
                                        action.kind === 'accept_scope'
                                            ? 'green'
                                            : action.kind === 'request_photo'
                                              ? 'amber'
                                              : 'blue'
                                    }
                                >
                                    {action.label}
                                    {action.adjustedPrice
                                        ? ` to ${action.adjustedPrice}`
                                        : ''}
                                </Badge>
                                {action.reason && <span>{action.reason}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <PipelinePanel pipeline={pipeline} />
        </Layout>
    );
}

function ProActions({ requestId }: { requestId: string }) {
    const [accepting, setAccepting] = useState(false);
    const form = useForm<{
        kind: ProActionKind | '';
        reason: string;
        adjusted_price: string;
    }>({ kind: '', reason: '', adjusted_price: '' });

    function toggle(kind: ProActionKind) {
        form.setData({
            kind: form.data.kind === kind ? '' : kind,
            reason: '',
            adjusted_price: '',
        });
        form.clearErrors();
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/requests/${requestId}/pro/actions`, {
            onSuccess: () => form.reset(),
        });
    }

    const openClass = (kind: ProActionKind) =>
        form.data.kind === kind
            ? 'border-blue-600 ring-2 ring-blue-600/20'
            : '';

    return (
        <div className="mt-4">
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    disabled={accepting}
                    onClick={() =>
                        router.post(
                            `/requests/${requestId}/pro/actions`,
                            { kind: 'accept_scope' },
                            {
                                onStart: () => setAccepting(true),
                                onFinish: () => setAccepting(false),
                            },
                        )
                    }
                    className={buttonPrimary}
                >
                    Accept scope
                </button>
                <button
                    type="button"
                    onClick={() => toggle('request_photo')}
                    aria-expanded={form.data.kind === 'request_photo'}
                    className={`${buttonSecondary} py-2 ${openClass('request_photo')}`}
                >
                    Request photo
                </button>
                <button
                    type="button"
                    onClick={() => toggle('adjust_quote')}
                    aria-expanded={form.data.kind === 'adjust_quote'}
                    className={`${buttonSecondary} py-2 ${openClass('adjust_quote')}`}
                >
                    Adjust quote
                </button>
            </div>
            {form.errors.kind && (
                <p className="mt-2 text-sm text-red-700">{form.errors.kind}</p>
            )}
            {(form.data.kind === 'request_photo' ||
                form.data.kind === 'adjust_quote') && (
                <form
                    onSubmit={submit}
                    className="mt-3 grid gap-3 rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm sm:grid-cols-3"
                >
                    {form.data.kind === 'adjust_quote' && (
                        <label className="block">
                            <span className="text-xs font-medium text-stone-600">
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
                                className={`mt-1 ${inputClass}`}
                            />
                            {form.errors.adjusted_price && (
                                <span className="mt-1 block text-red-700">
                                    {form.errors.adjusted_price}
                                </span>
                            )}
                        </label>
                    )}
                    <label
                        className={`block ${form.data.kind === 'adjust_quote' ? 'sm:col-span-2' : 'sm:col-span-3'}`}
                    >
                        <span className="text-xs font-medium text-stone-600">
                            {form.data.kind === 'request_photo'
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
                            className={`mt-1 ${inputClass}`}
                        />
                        {form.errors.reason && (
                            <span className="mt-1 block text-red-700">
                                {form.errors.reason}
                            </span>
                        )}
                    </label>
                    <div className="sm:col-span-3">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className={buttonPrimary}
                        >
                            {form.data.kind === 'request_photo'
                                ? 'Send photo request'
                                : 'Save adjustment'}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
