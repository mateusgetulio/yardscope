import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PipelinePanel from '@/components/pipeline-panel';
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

const dispositionStyles: Record<ScopeLineView['disposition'], string> = {
    priceable: 'border-green-300 bg-green-50',
    needs_photos: 'border-amber-300 bg-amber-50',
    manual_quote: 'border-stone-300 bg-stone-100',
    suggested: 'border-blue-200 bg-blue-50',
    rejected: 'border-stone-200 bg-white text-stone-500',
};

const sizes: string[] = ['small', 'medium', 'large'];
const severities: string[] = ['light', 'moderate', 'heavy'];

export default function ResultPage({ request, pipeline }: Props) {
    const { errors } = usePage().props;
    const [booking, setBooking] = useState(false);

    return (
        <>
            <Head title="Your job" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <p className="text-sm text-stone-500">
                    <Link href="/">Start over</Link>
                </p>
                <h1 className="mt-2 text-2xl font-semibold">
                    {request.booked ? 'Booked' : request.readinessLabel}
                </h1>
                <p className="mt-1 text-stone-600">“{request.sentence}”</p>

                {request.proMessage && (
                    <p className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm">
                        {request.proMessage}
                    </p>
                )}

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

                {errors.correction && (
                    <p className="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                        {errors.correction}
                    </p>
                )}
                {errors.photo && (
                    <p className="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                        {errors.photo}
                    </p>
                )}

                <section className="mt-8 space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide text-stone-500 uppercase">
                        Here is what we found
                    </h2>
                    {request.lines.map((line) => (
                        <LineCard key={line.id} line={line} request={request} />
                    ))}
                </section>

                {request.rejected.length > 0 && (
                    <p className="mt-4 text-sm text-stone-500">
                        Left out because the analysis could not be read:{' '}
                        {request.rejected
                            .map((line) => line.label.toLowerCase())
                            .join(', ')}
                        . A pro can look at it on site.
                    </p>
                )}

                {request.unsupportedRequests.length > 0 && (
                    <p className="mt-4 text-sm text-stone-500">
                        Not offered yet:{' '}
                        {request.unsupportedRequests.join(', ')}.
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
                    {errors.booking && (
                        <p className="mt-2 text-sm text-red-700">
                            {errors.booking}
                        </p>
                    )}
                    {request.booked ? (
                        <p className="mt-4 text-sm">
                            Booked at {request.bookedPrice}.{' '}
                            <Link
                                href={`/requests/${request.id}/booked`}
                                className="underline"
                            >
                                See the confirmation
                            </Link>
                        </p>
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
                            className="mt-4 rounded-lg bg-blue-700 px-5 py-2 font-medium text-white disabled:bg-stone-300 disabled:text-stone-600"
                        >
                            {request.cta.label}
                        </button>
                    )}
                </section>

                <PipelinePanel pipeline={pipeline} />
            </main>
        </>
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

    return (
        <article
            className={`rounded-lg border p-4 ${dispositionStyles[line.disposition]}`}
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 className="font-semibold">
                        {line.label}
                        {line.section ? `, ${line.section}` : ''}
                    </h3>
                    <p className="text-sm">{line.summary}</p>
                    {line.origin === 'customer_corrected' && (
                        <p className="text-xs text-stone-500">
                            You corrected this
                            {line.observedSummary
                                ? ` from “${line.observedSummary}”`
                                : ''}
                            {line.lastReason ? `: ${line.lastReason}` : '.'}
                        </p>
                    )}
                </div>
                <div className="text-right text-sm">
                    <p className="font-medium">{line.dispositionLabel}</p>
                    {line.hours && <p>{line.hours}</p>}
                    {line.labor && <p>{line.labor} labor</p>}
                </div>
            </div>
            {line.note && <p className="mt-2 text-sm">{line.note}</p>}
            {line.photoRequest && (
                <p className="mt-2 text-sm font-medium">{line.photoRequest}</p>
            )}
            <p className="mt-2 text-xs text-stone-500">
                {line.checksPassed} of {line.checksTotal} checks passed
                {line.evidence.length > 0 &&
                    `, seen in photo ${[...new Set(line.evidence.map((item) => item.photo))].join(', ')}`}
            </p>

            {editable && (
                <div className="mt-3 flex flex-wrap gap-2 text-sm">
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
                            label={line.removed ? 'Put it back' : 'Add to job'}
                        />
                    )}
                    {line.canChange && (
                        <button
                            type="button"
                            onClick={() => setEditing((open) => !open)}
                            className="rounded border border-stone-400 bg-white px-3 py-1"
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
                            label={line.placeholder ? 'Skip this' : 'Remove'}
                        />
                    )}
                    {line.disposition === 'needs_photos' &&
                        request.canAddPhoto && (
                            <AddPhoto requestId={request.id} />
                        )}
                </div>
            )}

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
            className="rounded border border-stone-400 bg-white px-3 py-1 disabled:opacity-60"
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
            className="mt-3 grid gap-3 rounded border border-stone-300 bg-white p-3 text-sm sm:grid-cols-3"
        >
            <label className="block">
                <span className="text-xs text-stone-500">What is off?</span>
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
                    className="mt-1 w-full rounded border border-stone-300 px-2 py-1"
                >
                    {fields.map((field) => (
                        <option key={field} value={field}>
                            {field === 'quantity' ? 'count' : field}
                        </option>
                    ))}
                </select>
            </label>
            <label className="block">
                <span className="text-xs text-stone-500">
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
                        className="mt-1 w-full rounded border border-stone-300 px-2 py-1"
                    />
                ) : (
                    <select
                        value={form.data.customer_value}
                        onChange={(event) =>
                            form.setData('customer_value', event.target.value)
                        }
                        className="mt-1 w-full rounded border border-stone-300 px-2 py-1"
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
                <span className="text-xs text-stone-500">Why (optional)</span>
                <input
                    type="text"
                    maxLength={200}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    placeholder="Two more behind the shed"
                    className="mt-1 w-full rounded border border-stone-300 px-2 py-1"
                />
            </label>
            <div className="sm:col-span-3">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded bg-blue-700 px-4 py-1 font-medium text-white disabled:opacity-60"
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
        <span className="inline-flex flex-wrap items-center gap-2">
            <label className="cursor-pointer rounded border border-amber-500 bg-white px-3 py-1">
                {sending ? 'Analyzing again' : 'Add photo'}
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
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
            <span className="text-xs text-stone-500">
                Runs the analysis again; your corrections are kept where they
                still apply.
            </span>
        </span>
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
