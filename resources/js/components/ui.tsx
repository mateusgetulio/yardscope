import type { ReactNode } from 'react';
import { toneClasses, type Tone } from '@/lib/styles';

export function Badge({ tone, children }: { tone: Tone; children: ReactNode }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset ${toneClasses[tone]}`}
        >
            {children}
        </span>
    );
}

export function SectionTitle({ children }: { children: ReactNode }) {
    return (
        <h2 className="text-xs font-semibold tracking-wider text-stone-500 uppercase">
            {children}
        </h2>
    );
}

export function Notice({
    tone,
    children,
}: {
    tone: 'amber' | 'blue' | 'red' | 'stone';
    children: ReactNode;
}) {
    const classes = {
        amber: 'border-amber-200 bg-amber-50 text-amber-950',
        blue: 'border-blue-200 bg-blue-50 text-blue-950',
        red: 'border-red-200 bg-red-50 text-red-900',
        stone: 'border-stone-200 bg-stone-100 text-stone-800',
    }[tone];

    return (
        <div className={`rounded-lg border px-4 py-3 text-sm ${classes}`}>
            {children}
        </div>
    );
}

export function PhotoNumber({ number }: { number: number }) {
    return (
        <span className="absolute top-1.5 left-1.5 rounded-md bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white">
            {number}
        </span>
    );
}

export function PhotoGrid({
    photos,
}: {
    photos: { number: number; url: string }[];
}) {
    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {photos.map((photo) => (
                <a
                    key={photo.number}
                    href={photo.url}
                    target="_blank"
                    rel="noreferrer"
                    className="relative block overflow-hidden rounded-lg ring-1 ring-stone-200"
                >
                    <img
                        src={photo.url}
                        alt={`Photo ${photo.number}`}
                        className="aspect-[4/3] w-full object-cover transition-transform hover:scale-[1.02]"
                    />
                    <PhotoNumber number={photo.number} />
                </a>
            ))}
        </div>
    );
}
