import { useState } from 'react';
import type { PipelineView } from '@/types/scope';

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;

export default function PipelinePanel({
    pipeline,
}: {
    pipeline: PipelineView;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section className="mt-8 rounded-lg border border-stone-300 bg-white">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold"
                aria-expanded={open}
            >
                <span>How this request was processed</span>
                <span className="text-stone-500">{open ? 'Hide' : 'Show'}</span>
            </button>
            {open && (
                <div className="space-y-5 border-t border-stone-200 px-4 py-4 text-sm">
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
                            <p>Model note: {pipeline.validation.requestNote}</p>
                        )}
                    </Stage>
                    <Stage title="Dispositions">
                        <ul className="space-y-2">
                            {pipeline.dispositions.map((line) => (
                                <li key={line.id}>
                                    <span className="font-medium">
                                        {line.id} {line.label}:{' '}
                                        {line.disposition}
                                    </span>
                                    <ul className="ml-4 list-disc">
                                        {line.checks.map((check) => (
                                            <li key={check.rule}>
                                                {check.rule}{' '}
                                                {check.passed
                                                    ? 'passed'
                                                    : 'failed'}
                                                : {check.message}
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
                                <ul className="space-y-1">
                                    {pipeline.pricing.lines.map((line) => (
                                        <li key={line.id}>
                                            {line.id}:{' '}
                                            {line.lowHours.toFixed(2)} to{' '}
                                            {line.highHours.toFixed(2)} h,{' '}
                                            {money(line.laborCents)} labor
                                        </li>
                                    ))}
                                </ul>
                                <p>
                                    Total {pipeline.pricing.lowHours.toFixed(3)}{' '}
                                    to {pipeline.pricing.highHours.toFixed(3)}{' '}
                                    h, shown as {pipeline.pricing.shownLowHours}{' '}
                                    to {pipeline.pricing.shownHighHours} h;
                                    midpoint labor plus{' '}
                                    {money(pipeline.pricing.visitFeeCents)}{' '}
                                    visit fee, rounded up to{' '}
                                    {money(pipeline.pricing.priceCents)}.
                                </p>
                            </>
                        )}
                    </Stage>
                    <Stage title="Corrections">
                        {pipeline.corrections.length === 0 ? (
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
                            </ul>
                        )}
                    </Stage>
                </div>
            )}
        </section>
    );
}

function Stage({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <h3 className="text-xs font-semibold tracking-wide text-stone-500 uppercase">
                {title}
            </h3>
            <div className="mt-1 space-y-1">{children}</div>
        </div>
    );
}
