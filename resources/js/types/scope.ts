export type Disposition =
    'priceable' | 'needs_photos' | 'manual_quote' | 'suggested' | 'rejected';

export type Readiness = 'ready' | 'partial' | 'needs_photos' | 'manual_quote';

export interface ReadinessCheck {
    rule: string;
    passed: boolean;
    message: string;
}

export interface Evidence {
    photo: number;
    note: string;
}

export interface ScopeLineView {
    id: string;
    type: string;
    label: string;
    section: string | null;
    disposition: Disposition;
    dispositionLabel: string;
    summary: string;
    quantity: number | null;
    size: string | null;
    severity: string | null;
    counted: boolean;
    usesSize: boolean;
    origin: 'ai_observed' | 'customer_corrected';
    observedSummary: string | null;
    note: string | null;
    photoRequest: string | null;
    checksPassed: number;
    checksTotal: number;
    checks: ReadinessCheck[];
    evidence: Evidence[];
    hours: string | null;
    labor: string | null;
    removed: boolean;
    placeholder: boolean;
    canRemove: boolean;
    canAdd: boolean;
    canChange: boolean;
    lastReason: string | null;
}

export interface EstimateView {
    priceCents: number;
    price: string;
    hours: string;
    visitFee: string;
}

export interface RequestView {
    id: string;
    sentence: string;
    booked: boolean;
    bookedPrice: string | null;
    photos: { number: number; url: string }[];
    canAddPhoto: boolean;
    profile: Record<string, string>;
    readiness: Readiness;
    readinessLabel: string;
    requestNote: string | null;
    estimate: EstimateView | null;
    cta: { label: string; enabled: boolean };
    excludedSummary: string | null;
    lines: ScopeLineView[];
    rejected: { type: string; reason: string }[];
    access: { narrowGatePossible: boolean };
    hazards: { section: string; note: string }[];
    unsupportedRequests: string[];
}

export type CorrectionField =
    'quantity' | 'size' | 'severity' | 'removed' | 'added';

export interface CorrectionInput {
    line_id: string;
    field: CorrectionField;
    model_value: string;
    customer_value: string;
    reason: string;
}
