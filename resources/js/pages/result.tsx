import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '@/components/layout';
import PipelinePanel from '@/components/pipeline-panel';
import { Badge, Notice, PhotoGrid, SectionTitle } from '@/components/ui';
import {
    buttonPrimary,
    buttonSecondary,
    cardClass,
    dispositionTone,
    inputClass,
    readinessTone,
} from '@/lib/styles';
import type {
    CorrectionInput,
    PipelineView,
    RequestView,
    ScopeLineView,
} from '@/types/scope';

interface Props {
    request: RequestView;
    pipeline: PipelineView;
}

const accent: Record<ScopeLineView['disposition'], string> = {
    priceable: 'border-l-green-500',
    needs_photos: 'border-l-amber-400',
    manual_quote: 'border-l-stone-400',
    suggested: 'border-l-blue-400',
    rejected: 'border-l-stone-200',
};

const sizes: string[] = ['small', 'medium', 'large'];
const severities: string[] = ['light', 'moderate', 'heavy'];

export default function ResultPage({ request, pipeline }: Props) {
    const { errors } = usePage().props;
    const [booking, setBooking] = useState(false);

    return (
        <Layout title="Your job">
            <div className="flex flex-wrap items-center gap-2">
                <Badge tone={readinessTone[request.readiness]}>
                    {request.readinessLabel}
                </Badge>
                {request.booked && (
                    <Badge tone="green">Booked at {request.bookedPrice}</Badge>
                )}
            </div>
            <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                {headline(request)}
            </h1>
            <blockquote className="mt-3 border-l-2 border-stone-300 pl-3 text-stone-600">
                “{request.sentence}”
            </blockquote>

            <div className="mt-6 space-y-3">
                {request.proMessage && (
                    <Notice tone="blue">{request.proMessage}</Notice>
                )}
                {request.requestNote && (
                    <ModelNote note={request.requestNote} />
                )}
                {errors.correction && (
                    <Notice tone="red">{errors.correction}</Notice>
                )}
                {errors.photo && <Notice tone="red">{errors.photo}</Notice>}
            </div>

            <section className="mt-6">
                <PhotoGrid photos={request.photos} />
            </section>

            <section className="mt-10 space-y-3">
                <SectionTitle>Here is what we found</SectionTitle>
                {request.lines.map((line) => (
                    <LineCard key={line.id} line={line} request={request} />
                ))}
                {request.rejected.length > 0 && (
                    <p className="text-sm text-stone-500">
                        Left out because the analysis could not be read:{' '}
                        {request.rejected
                            .map((line) => line.label.toLowerCase())
                            .join(', ')}
                        . A pro can look at it on site.
                    </p>
                )}
                {request.unsupportedRequests.length > 0 && (
                    <p className="text-sm text-stone-500">
                        Not offered yet:{' '}
                        {request.unsupportedRequests.join(', ')}.
                    </p>
                )}
            </section>

            {request.access.narrowGatePossible && (
                <div className="mt-6">
                    <Notice tone="amber">
                        <span className="font-medium">
                            Your side gate may be narrow.
                        </span>{' '}
                        Is it at least 36 inches wide? Your pro will confirm
                        before bringing equipment.
                    </Notice>
                </div>
            )}

            <section className={`mt-8 p-5 sm:p-6 ${cardClass}`}>
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        {request.estimate ? (
                            <>
                                <p className="text-xs font-medium text-stone-500">
                                    Estimate
                                </p>
                                <p className="text-4xl font-semibold tracking-tight tabular-nums">
                                    {request.estimate.price}
                                </p>
                                <p className="mt-1 text-sm text-stone-600">
                                    {request.estimate.hours} of work, including
                                    a {request.estimate.visitFee} visit fee
                                </p>
                            </>
                        ) : (
                            <p className="text-lg font-medium text-stone-600">
                                No price yet
                            </p>
                        )}
                        {request.excludedSummary && (
                            <p className="mt-2 text-sm text-stone-600">
                                {request.excludedSummary}
                            </p>
                        )}
                        <p className="mt-2 text-xs text-stone-400">
                            Synthetic demo rates.
                        </p>
                    </div>
                    <div className="shrink-0">
                        {request.booked ? (
                            <Link
                                href={`/requests/${request.id}/booked`}
                                className={`${buttonSecondary} w-full py-2 sm:w-auto`}
                            >
                                See the confirmation
                            </Link>
                        ) : (
                            <button
                                type="button"
                                disabled={!request.cta.enabled || booking}
                                onClick={() =>
                                    router.post(
                                        `/requests/${request.id}/book`,
                                        {},
                                        {
                                            onStart: () => setBooking(true),
                                            onFinish: () => setBooking(false),
                                        },
                                    )
                                }
                                className={`${buttonPrimary} w-full px-5 py-2.5 text-base sm:w-auto`}
                            >
                                {request.cta.label}
                            </button>
                        )}
                    </div>
                </div>
                {errors.booking && (
                    <p className="mt-3 text-sm text-red-700">
                        {errors.booking}
                    </p>
                )}
            </section>

            <PipelinePanel pipeline={pipeline} />
        </Layout>
    );
}

function headline(request: RequestView): string {
    if (request.booked) {
        return 'Your job is booked';
    }

    switch (request.readiness) {
        case 'ready':
            return 'Everything you asked for is priced';
        case 'partial':
            return 'Part of the job is priced now';
        case 'needs_photos':
            return 'We need a photo before we can price this';
        default:
            return 'A pro will quote this on site';
    }
}

/** The model's own note can run long; show the start and let the customer open the rest. */
function ModelNote({ note }: { note: string }) {
    const [open, setOpen] = useState(false);
    const long = note.length > 220;

    return (
        <Notice tone="stone">
            <p className={open || !long ? '' : 'line-clamp-2'}>
                <span className="font-medium">From the photo analysis: </span>
                {note}
            </p>
            {long && (
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="mt-1 text-xs font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900"
                >
                    {open ? 'Show less' : 'Show the full note'}
                </button>
            )}
        </Notice>
    );
}

function LineCard({
    line,
    request,
}: {
    line: ScopeLineView;
    request: RequestView;
}) {
    const [editing, setEditing] = useState(false);
    const editable = !request.booked;
    const photos = [...new Set(line.evidence.map((item) => item.photo))];

    return (
        <article
            className={`border-l-4 p-4 ${cardClass} ${accent[line.disposition]} ${line.disposition === 'rejected' ? 'text-stone-500' : ''}`}
        >
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    <h3 className="font-semibold">
                        {line.label}
                        {line.section ? (
                            <span className="font-normal text-stone-500">
                                , {line.section}
                            </span>
                        ) : null}
                    </h3>
                    <p className="text-sm">{line.summary}</p>
                    {line.origin === 'customer_corrected' && (
                        <p className="mt-0.5 text-xs text-amber-800">
                            You corrected this
                            {line.observedSummary
                                ? ` from “${line.observedSummary}”`
                                : ''}
                            {line.lastReason ? `: ${line.lastReason}` : '.'}
                        </p>
                    )}
                </div>
                <div className="flex flex-row-reverse flex-wrap items-center justify-end gap-2 sm:flex-col sm:items-end sm:gap-1 sm:text-right">
                    <Badge tone={dispositionTone[line.disposition]}>
                        {line.dispositionLabel}
                    </Badge>
                    {line.hours && (
                        <p className="text-sm text-stone-600 tabular-nums">
                            {line.hours}
                            {line.labor ? `, ${line.labor} labor` : ''}
                        </p>
                    )}
                </div>
            </div>
            {line.note && <p className="mt-2 text-sm">{line.note}</p>}
            {line.photoRequest && (
                <p className="mt-2 text-sm font-medium text-amber-900">
                    {line.photoRequest}
                </p>
            )}

            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-stone-500">
                    {line.checksPassed} of {line.checksTotal} checks passed
                    {photos.length > 0 &&
                        `, seen in photo ${photos.join(', ')}`}
                </p>
                {editable && (
                    <div className="flex flex-wrap items-center gap-2">
                        {line.canAdd && (
                            <CorrectionButton
                                requestId={request.id}
                                input={{
                                    line_id: line.id,
                                    field: 'added',
                                    model_value: '',
                                    customer_value: '',
                                    reason: '',
                                }}
                                label={
                                    line.removed ? 'Put it back' : 'Add to job'
                                }
                            />
                        )}
                        {line.disposition === 'needs_photos' &&
                            request.canAddPhoto && (
                                <AddPhoto requestId={request.id} />
                            )}
                        {line.canChange && (
                            <button
                                type="button"
                                onClick={() => setEditing((open) => !open)}
                                aria-expanded={editing}
                                className={buttonSecondary}
                            >
                                {editing ? 'Cancel' : 'Correct this'}
                            </button>
                        )}
                        {line.canRemove && (
                            <CorrectionButton
                                requestId={request.id}
                                input={{
                                    line_id: line.id,
                                    field: 'removed',
                                    model_value: '',
                                    customer_value: '',
                                    reason: '',
                                }}
                                label={
                                    line.placeholder ? 'Skip this' : 'Remove'
                                }
                            />
                        )}
                    </div>
                )}
            </div>

            {editing && (
                <CorrectionForm
                    line={line}
                    requestId={request.id}
                    onDone={() => setEditing(false)}
                />
            )}
        </article>
    );
}

function CorrectionButton({
    requestId,
    input,
    label,
}: {
    requestId: string;
    input: CorrectionInput;
    label: string;
}) {
    const form = useForm<CorrectionInput>(input);

    return (
        <button
            type="button"
            disabled={form.processing}
            onClick={() => form.post(`/requests/${requestId}/corrections`)}
            className={buttonSecondary}
        >
            {label}
        </button>
    );
}

function CorrectionForm({
    line,
    requestId,
    onDone,
}: {
    line: ScopeLineView;
    requestId: string;
    onDone: () => void;
}) {
    const initialField: CorrectionInput['field'] = line.counted
        ? 'quantity'
        : 'severity';
    const form = useForm<CorrectionInput>({
        line_id: line.id,
        field: initialField,
        model_value: currentValue(line, initialField),
        customer_value: currentValue(line, initialField),
        reason: '',
    });

    function chooseField(field: CorrectionInput['field']) {
        form.setData({
            ...form.data,
            field,
            model_value: currentValue(line, field),
            customer_value: currentValue(line, field),
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/requests/${requestId}/corrections`, {
            onSuccess: onDone,
        });
    }

    const fields: CorrectionInput['field'][] = [
        ...(line.counted ? (['quantity'] as const) : []),
        ...(line.usesSize ? (['size'] as const) : []),
        ...(line.severity !== null ? (['severity'] as const) : []),
    ];

    return (
        <form
            onSubmit={submit}
            className="mt-3 grid gap-3 rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm sm:grid-cols-3"
        >
            <label className="block">
                <span className="text-xs font-medium text-stone-600">
                    What is off?
                </span>
                <select
                    value={form.data.field}
                    onChange={(event) => {
                        const chosen = fields.find(
                            (field) => field === event.target.value,
                        );
                        if (chosen !== undefined) {
                            chooseField(chosen);
                        }
                    }}
                    className={`mt-1 ${inputClass}`}
                >
                    {fields.map((field) => (
                        <option key={field} value={field}>
                            {field === 'quantity' ? 'count' : field}
                        </option>
                    ))}
                </select>
            </label>
            <label className="block">
                <span className="text-xs font-medium text-stone-600">
                    Should be (now {form.data.model_value})
                </span>
                {form.data.field === 'quantity' ? (
                    <input
                        type="number"
                        min={1}
                        max={20}
                        value={form.data.customer_value}
                        onChange={(event) =>
                            form.setData('customer_value', event.target.value)
                        }
                        className={`mt-1 ${inputClass}`}
                    />
                ) : (
                    <select
                        value={form.data.customer_value}
                        onChange={(event) =>
                            form.setData('customer_value', event.target.value)
                        }
                        className={`mt-1 ${inputClass}`}
                    >
                        {(form.data.field === 'size' ? sizes : severities).map(
                            (option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ),
                        )}
                    </select>
                )}
            </label>
            <label className="block">
                <span className="text-xs font-medium text-stone-600">
                    Why (optional)
                </span>
                <input
                    type="text"
                    maxLength={200}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    placeholder="Two more behind the shed"
                    className={`mt-1 ${inputClass}`}
                />
            </label>
            <div className="sm:col-span-3">
                <button
                    type="submit"
                    disabled={form.processing}
                    className={buttonPrimary}
                >
                    Save correction
                </button>
            </div>
        </form>
    );
}

function AddPhoto({ requestId }: { requestId: string }) {
    const [sending, setSending] = useState(false);

    return (
        <label
            title="Runs the analysis again; your corrections are kept where they still apply."
            className={`${buttonSecondary} cursor-pointer border-amber-400 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-blue-600 ${sending ? 'opacity-60' : ''}`}
        >
            {sending ? 'Analyzing again…' : 'Add photo'}
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                disabled={sending}
                onChange={(event) => {
                    const photo = event.target.files?.[0];
                    if (photo === undefined) {
                        return;
                    }
                    router.post(
                        `/requests/${requestId}/photos`,
                        { photo },
                        {
                            forceFormData: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                        },
                    );
                }}
            />
        </label>
    );
}

function currentValue(
    line: ScopeLineView,
    field: CorrectionInput['field'],
): string {
    switch (field) {
        case 'quantity':
            return line.quantity === null ? '' : String(line.quantity);
        case 'size':
            return line.size ?? '';
        case 'severity':
            return line.severity ?? '';
        default:
            return '';
    }
}
