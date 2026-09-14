import type { Disposition, Readiness } from '@/types/scope';

export type Tone = 'green' | 'amber' | 'stone' | 'blue' | 'red';

export const toneClasses: Record<Tone, string> = {
    green: 'bg-green-100 text-green-800 ring-green-600/20',
    amber: 'bg-amber-100 text-amber-900 ring-amber-600/20',
    stone: 'bg-stone-100 text-stone-700 ring-stone-500/20',
    blue: 'bg-blue-100 text-blue-800 ring-blue-600/20',
    red: 'bg-red-100 text-red-800 ring-red-600/20',
};

export const dispositionTone: Record<Disposition, Tone> = {
    priceable: 'green',
    needs_photos: 'amber',
    manual_quote: 'stone',
    suggested: 'blue',
    rejected: 'stone',
};

export const readinessTone: Record<Readiness, Tone> = {
    ready: 'green',
    partial: 'green',
    needs_photos: 'amber',
    manual_quote: 'stone',
};

const focus =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600';

export const buttonPrimary = `inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-stone-300 disabled:text-stone-600 disabled:shadow-none ${focus}`;

export const buttonSecondary = `inline-flex items-center justify-center rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-60 ${focus}`;

export const inputClass = `block w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-stone-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none`;

export const cardClass =
    'rounded-xl border border-stone-200 bg-white shadow-sm';
