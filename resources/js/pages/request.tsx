import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

interface Props {
    profile: Record<string, string>;
    extractor: 'fixtures' | 'api' | 'claude-code';
}

export default function RequestPage({ profile, extractor }: Props) {
    const form = useForm<{ sentence: string; photos: File[] }>({
        sentence: '',
        photos: [],
    });
    const [previews, setPreviews] = useState<string[]>([]);

    // Object URLs live as long as the selection they preview; the cleanup revokes the previous set.
    useEffect(
        () => () => previews.forEach((url) => URL.revokeObjectURL(url)),
        [previews],
    );

    function choosePhotos(files: File[]) {
        form.setData('photos', files);
        setPreviews(files.map((photo) => URL.createObjectURL(photo)));
    }

    const photoErrors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('photos'))
        .map(([, message]) => message);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/requests', { forceFormData: true });
    }

    return (
        <>
            <Head title="Request" />
            <main className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-2xl font-semibold">
                    Turn yard photos into a bookable job
                </h1>
                <p className="mt-2 text-stone-600">
                    Tell us what you need done and add two to four photos. We
                    price what the photos show and ask for more when they do not
                    show enough.
                </p>

                <form onSubmit={submit} className="mt-8 space-y-6">
                    <div>
                        <label
                            htmlFor="sentence"
                            className="block text-sm font-medium"
                        >
                            What do you need done?
                        </label>
                        <textarea
                            id="sentence"
                            value={form.data.sentence}
                            onChange={(event) =>
                                form.setData('sentence', event.target.value)
                            }
                            rows={3}
                            placeholder="My backyard is a mess. Clean it up and trim whatever needs trimming."
                            className="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2"
                        />
                        {form.errors.sentence && (
                            <p className="mt-1 text-sm text-red-700">
                                {form.errors.sentence}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            htmlFor="photos"
                            className="block text-sm font-medium"
                        >
                            Photos (2 to 4)
                        </label>
                        <input
                            id="photos"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            onChange={(event) =>
                                choosePhotos(
                                    Array.from(event.target.files ?? []).slice(
                                        0,
                                        4,
                                    ),
                                )
                            }
                            className="mt-1 block w-full text-sm"
                        />
                        <div className="mt-2 grid grid-cols-4 gap-2">
                            {[0, 1, 2, 3].map((slot) =>
                                previews[slot] ? (
                                    <img
                                        key={slot}
                                        src={previews[slot]}
                                        alt={`Photo ${slot + 1}`}
                                        className="aspect-[4/3] w-full rounded-lg object-cover"
                                    />
                                ) : (
                                    <div
                                        key={slot}
                                        className="flex aspect-[4/3] items-center justify-center rounded-lg border border-dashed border-stone-300 text-xs text-stone-400"
                                    >
                                        {slot < 2 ? 'Required' : 'Optional'}
                                    </div>
                                ),
                            )}
                        </div>
                        <p className="mt-1 text-sm text-stone-500">
                            Location data is removed from every photo.
                        </p>
                        {photoErrors.map((message) => (
                            <p
                                key={message}
                                className="mt-1 text-sm text-red-700"
                            >
                                {message}
                            </p>
                        ))}
                    </div>

                    <p className="text-sm text-stone-500">
                        Simulated property: {describeProfile(profile)}. Rates
                        are synthetic demo rates, not real prices.
                        {extractorNote(extractor)}
                    </p>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-lg bg-blue-700 px-5 py-2 font-medium text-white disabled:opacity-60"
                    >
                        {form.processing
                            ? 'Analyzing photos'
                            : 'Analyze my yard'}
                    </button>
                </form>
            </main>
        </>
    );
}

function describeProfile(profile: Record<string, string>): string {
    return Object.entries(profile)
        .map(([section, size]) => `${size} ${section.replace('_', ' ')}`)
        .join(', ');
}

function extractorNote(extractor: Props['extractor']): string {
    switch (extractor) {
        case 'api':
            return ' Photos are sent to the vision model.';
        case 'claude-code':
            return ' Photos are analyzed through the local Claude Code session.';
        default:
            return ' Running on recorded analyses, no model call.';
    }
}
