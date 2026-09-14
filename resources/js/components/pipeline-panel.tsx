import { useState, type ReactNode } from 'react';
import { cardClass } from '@/lib/styles';
import type { PipelineView } from '@/types/scope';

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;

export default function PipelinePanel({
    pipeline,
}: {
    pipeline: PipelineView;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section className={`mt-10 overflow-hidden ${cardClass}`}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between gap-4 px-5 py-3.5 text-left hover:bg-stone-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-600"
                aria-expanded={open}
            >
                <span>
                    <span className="block text-sm font-semibold">
                        How this request was processed
                    </span>
                    <span className="block text-xs text-stone-500">
                        Photos, extraction, validation, dispositions, pricing
                        and corrections, with the actual values
                    </span>
                </span>
                <span
                    aria-hidden="true"
                    className={`text-lg leading-none text-stone-400 transition-transform ${open ? 'rotate-90' : ''}`}
                >
                    ▸
                </span>
            </button>
            {open && (
                <div className="divide-y divide-stone-100 border-t border-stone-200 bg-stone-50/60 text-sm">
                    <Stage title="Photos">
                        <ul className="space-y-1">
                            {pipeline.photos.map((photo) => (
                                <li key={photo.number}>
                                    Photo {photo.number}:{' '}
                                    {photo.view === null
                                        ? 'not described'
                                        : `${photo.view} view of ${photo.sections.length > 0 ? photo.sections.join(', ').replaceAll('_', ' ') : 'no section'}, ${photo.usable ? 'usable' : 'not usable'}`}
                                </li>
                            ))}
                        </ul>
                    </Stage>
                    <Stage title="Extraction">
                        <p>Driver: {pipeline.extraction.driver}</p>
                        <ul className="space-y-1">
                            {pipeline.extraction.runs.map((run) => (
                                <li key={run.id}>
                                    Run {run.id}: {run.photoCount} photos,{' '}
                                    {run.failure
                                        ? `failed (${run.failure})`
                                        : `${run.lines} service lines`}
                                </li>
                            ))}
                        </ul>
                    </Stage>
                    <Stage title="Validation">
                        {pipeline.validation.rejected.length === 0 &&
                        pipeline.validation.unsupportedRequests.length === 0 ? (
                            <p>
                                Every line passed the schema and evidence rules.
                            </p>
                        ) : (
                            <ul className="space-y-1">
                                {pipeline.validation.rejected.map(
                                    (line, index) => (
                                        <li key={`rejected-${index}`}>
                                            Rejected {line.type}: {line.reason}
                                        </li>
                                    ),
                                )}
                                {pipeline.validation.unsupportedRequests.map(
                                    (request) => (
                                        <li key={request}>
                                            Not offered: {request}
                                        </li>
                                    ),
                                )}
                            </ul>
                        )}
                        {pipeline.validation.requestNote && (
                            <p className="text-stone-600">
                                Model note: {pipeline.validation.requestNote}
                            </p>
                        )}
                    </Stage>
                    <Stage title="Dispositions">
                        <ul className="space-y-2">
                            {pipeline.dispositions.map((line) => (
                                <li key={line.id}>
                                    <span className="font-medium">
                                        {line.label}
                                    </span>{' '}
                                    <span className="text-stone-500">
                                        {line.id}, {line.disposition}
                                    </span>
                                    <ul className="mt-0.5 space-y-0.5">
                                        {line.checks.map((check) => (
                                            <li
                                                key={check.rule}
                                                className="flex gap-2"
                                            >
                                                <span
                                                    className={`w-10 shrink-0 font-mono text-xs leading-5 ${check.passed ? 'text-green-700' : 'text-amber-700'}`}
                                                >
                                                    {check.passed ? '✓' : '✗'}{' '}
                                                    {check.rule}
                                                </span>
                                                <span>{check.message}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    </Stage>
                    <Stage title="Pricing">
                        {pipeline.pricing === null ? (
                            <p>No priceable line, so no price.</p>
                        ) : (
                            <>
                                <ul className="space-y-1 tabular-nums">
                                    {pipeline.pricing.lines.map((line) => (
                                        <li key={line.id}>
                                            {line.id}:{' '}
                                            {line.lowHours.toFixed(2)} to{' '}
                                            {line.highHours.toFixed(2)} h,{' '}
                                            {money(line.laborCents)} labor
                                        </li>
                                    ))}
                                </ul>
                                <p className="text-stone-600">
                                    Total {pipeline.pricing.lowHours.toFixed(3)}{' '}
                                    to {pipeline.pricing.highHours.toFixed(3)}{' '}
                                    h, shown as {pipeline.pricing.shownLowHours}{' '}
                                    to {pipeline.pricing.shownHighHours} h.
                                    Midpoint{' '}
                                    {pipeline.pricing.midpointHours.toFixed(4)}{' '}
                                    h at{' '}
                                    {money(pipeline.pricing.hourlyRateCents)}/h
                                    plus the{' '}
                                    {money(pipeline.pricing.visitFeeCents)}{' '}
                                    visit fee, rounded up to the next{' '}
                                    {money(pipeline.pricing.priceRoundingCents)}
                                    : {money(pipeline.pricing.priceCents)}.
                                </p>
                            </>
                        )}
                    </Stage>
                    <Stage title="Corrections">
                        {pipeline.corrections.length === 0 &&
                        pipeline.proActions.length === 0 ? (
                            <p>None.</p>
                        ) : (
                            <ul className="space-y-1">
                                {pipeline.corrections.map(
                                    (correction, index) => (
                                        <li key={index}>
                                            Run {correction.run},{' '}
                                            {correction.lineId}{' '}
                                            {correction.field}:{' '}
                                            {correction.modelValue || 'n/a'} to{' '}
                                            {correction.customerValue || 'n/a'}{' '}
                                            ({correction.source}
                                            {correction.reason
                                                ? `, ${correction.reason}`
                                                : ''}
                                            )
                                            {correction.skipped
                                                ? ', skipped'
                                                : ''}
                                            {correction.current
                                                ? ''
                                                : ', earlier run'}
                                        </li>
                                    ),
                                )}
                                {pipeline.proActions.map((action, index) => (
                                    <li key={`pro-${index}`}>
                                        Pro: {action.label.toLowerCase()}
                                        {action.adjustedPriceCents !== null
                                            ? ` to ${money(action.adjustedPriceCents)}`
                                            : ''}
                                        {action.reason
                                            ? `, ${action.reason}`
                                            : ''}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Stage>
                </div>
            )}
        </section>
    );
}

function Stage({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 px-5 py-4 sm:grid-cols-[7rem_1fr] sm:gap-4">
            <h3 className="text-xs font-semibold tracking-wider text-stone-500 uppercase sm:pt-0.5">
                {title}
            </h3>
            <div className="min-w-0 space-y-1 break-words">{children}</div>
        </div>
    );
}
