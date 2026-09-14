import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface Props {
    title: string;
    children: ReactNode;
}

/**
 * The frame every page shares: a thin top bar that says what this is and that the rates are not
 * real, then a single readable column.
 */
export default function Layout({ title, children }: Props) {
    return (
        <>
            <Head title={title} />
            <header className="border-b border-stone-200 bg-white">
                <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3">
                    <Link
                        href="/"
                        className="flex items-baseline gap-2 font-semibold tracking-tight text-stone-900"
                    >
                        YardScope
                        <span className="text-xs font-normal text-stone-500">
                            prototype, synthetic rates
                        </span>
                    </Link>
                    <nav className="flex gap-4 text-sm text-stone-600">
                        <Link href="/" className="hover:text-stone-900">
                            New request
                        </Link>
                        <Link href="/evals" className="hover:text-stone-900">
                            Evals
                        </Link>
                    </nav>
                </div>
            </header>
            <main className="mx-auto max-w-3xl px-4 pt-8 pb-16">
                {children}
            </main>
        </>
    );
}
