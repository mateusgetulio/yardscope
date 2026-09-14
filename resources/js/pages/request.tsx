import { Head } from '@inertiajs/react';

export default function RequestPage() {
    return (
        <>
            <Head title="Request" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-2xl font-semibold">
                    Turn yard photos into a bookable job
                </h1>
                <p className="mt-2 text-stone-600">
                    Tell us what you need done and add two to four photos.
                </p>
            </main>
        </>
    );
}
