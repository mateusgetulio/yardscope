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
    rejected: { label: string; reason: string }[];
    access: { narrowGatePossible: boolean };
    hazards: { section: string; note: string }[];
    unsupportedRequests: string[];
    proMessage: string | null;
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

export interface PipelineView {
    photos: {
        number: number;
        view: string | null;
        sections: string[];
        usable: boolean | null;
    }[];
    extraction: {
        driver: string;
        runs: {
            id: number;
            photoCount: number;
            failure: string | null;
            lines: number;
            at: string | null;
        }[];
    };
    validation: {
        rejected: { type: string; reason: string }[];
        unsupportedRequests: string[];
        requestNote: string | null;
    };
    dispositions: {
        id: string;
        label: string;
        disposition: Disposition;
        checks: ReadinessCheck[];
    }[];
    pricing: {
        lines: {
            id: string;
            lowHours: number;
            highHours: number;
            laborCents: number;
        }[];
        lowHours: number;
        highHours: number;
        shownLowHours: number;
        shownHighHours: number;
        midpointHours: number;
        hourlyRateCents: number;
        visitFeeCents: number;
        priceRoundingCents: number;
        priceCents: number;
    } | null;
    corrections: {
        run: number;
        lineId: string;
        field: CorrectionField;
        modelValue: string;
        customerValue: string;
        reason: string | null;
        source: string;
        skipped: boolean;
        current: boolean;
    }[];
    proActions: {
        kind: ProActionKind;
        label: string;
        reason: string | null;
        adjustedPriceCents: number | null;
        at: string;
    }[];
}

export type ProActionKind = 'accept_scope' | 'request_photo' | 'adjust_quote';

export interface BriefLineView {
    id: string;
    label: string;
    section: string | null;
    disposition: Disposition;
    dispositionLabel: string;
    summary: string;
    origin: 'ai_observed' | 'customer_corrected';
    originLabel: string;
    observedSummary: string | null;
    customerReason: string | null;
    removedByCustomer: boolean;
    evidence: Evidence[];
    note: string | null;
}

export interface ProActionView {
    id: number;
    kind: ProActionKind;
    label: string;
    reason: string | null;
    adjustedPrice: string | null;
    at: string;
}

export interface BriefView {
    id: string;
    sentence: string;
    readinessLabel: string;
    booked: boolean;
    bookedPrice: string | null;
    profile: Record<string, string>;
    lines: BriefLineView[];
    photos: { number: number; url: string; notes: string[] }[];
    accessNotes: string[];
    openQuestions: string[];
    estimate: { price: string; hours: string } | null;
    actions: ProActionView[];
}

export interface EvalResults {
    ran_at: string;
    mode: 'live' | 'fixtures';
    driver: string;
    metrics: Record<string, number | null>;
    sets: {
        slug: string;
        scenario: string;
        schema_valid: boolean;
        failure: string | null;
        expected_services: string[];
        observed_services: string[];
        hallucinated: string[];
        counts: {
            service: string;
            expected: number;
            observed: number | null;
            exact: boolean;
            withinOne: boolean;
        }[];
        dispositions: {
            service: string;
            expected: string;
            observed: string | null;
            correct: boolean;
        }[];
        expected_readiness: string | null;
        observed_readiness: string | null;
        readiness_correct: boolean | null;
        photo_request_correct: boolean | null;
        unusable_photos_correct: boolean | null;
    }[];
}
