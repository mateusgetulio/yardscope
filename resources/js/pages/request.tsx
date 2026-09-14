import { useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import Layout from '@/components/layout';
import { PhotoNumber } from '@/components/ui';
import { buttonPrimary, cardClass, inputClass } from '@/lib/styles';

interface Props {
    profile: Record<string, string>;
    extractor: 'fixtures' | 'api' | 'claude-code';
}

const MAX_SENTENCE = 300;

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
        <Layout title="Request">
            <h1 className="text-3xl font-semibold tracking-tight">
                Turn yard photos into a bookable job
            </h1>
            <p className="mt-3 text-stone-600">
                Tell us what you need done and add two to four photos. We price
                what the photos show and ask for more when they do not show
                enough.
            </p>

            <form
                onSubmit={submit}
                className={`mt-8 space-y-6 p-5 sm:p-6 ${cardClass}`}
            >
                <div>
                    <div className="flex items-baseline justify-between">
                        <label
                            htmlFor="sentence"
                            className="text-sm font-medium"
                        >
                            What do you need done?
                        </label>
                        <span className="text-xs text-stone-400">
                            {form.data.sentence.length}/{MAX_SENTENCE}
                        </span>
                    </div>
                    <textarea
                        id="sentence"
                        value={form.data.sentence}
                        maxLength={MAX_SENTENCE}
                        onChange={(event) =>
                            form.setData('sentence', event.target.value)
                        }
                        rows={3}
                        placeholder="My backyard is a mess. Clean it up and trim whatever needs trimming."
                        className={`mt-2 ${inputClass}`}
                    />
                    {form.errors.sentence && (
                        <p className="mt-1 text-sm text-red-700">
                            {form.errors.sentence}
                        </p>
                    )}
                </div>

                <div>
                    <div className="flex items-baseline justify-between">
                        <span className="text-sm font-medium">Photos</span>
                        <span className="text-xs text-stone-400">
                            2 to 4, JPEG, PNG or WebP
                        </span>
                    </div>
                    <label
                        htmlFor="photos"
                        className="mt-2 grid cursor-pointer grid-cols-2 gap-2 rounded-lg focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-blue-600 sm:grid-cols-4"
                    >
                        {[0, 1, 2, 3].map((slot) =>
                            previews[slot] ? (
                                <span key={slot} className="relative block">
                                    <img
                                        src={previews[slot]}
                                        alt={`Photo ${slot + 1}`}
                                        className="aspect-[4/3] w-full rounded-lg object-cover"
                                    />
                                    <PhotoNumber number={slot + 1} />
                                </span>
                            ) : (
                                <span
                                    key={slot}
                                    className="flex aspect-[4/3] flex-col items-center justify-center gap-0.5 rounded-lg border border-dashed border-stone-300 bg-stone-50 text-stone-500 transition-colors hover:border-blue-400 hover:bg-blue-50/50"
                                >
                                    <span className="text-lg leading-none text-stone-400">
                                        +
                                    </span>
                                    <span className="text-xs">
                                        Photo {slot + 1}
                                    </span>
                                    <span className="text-[11px] text-stone-400">
                                        {slot < 2 ? 'required' : 'optional'}
                                    </span>
                                </span>
                            ),
                        )}
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
                            className="sr-only"
                        />
                    </label>
                    <p className="mt-2 text-xs text-stone-500">
                        {previews.length > 0
                            ? 'Click the photos to choose a different set. '
                            : 'Choose all your photos at once. '}
                        Location data is removed from every photo.
                    </p>
                    {photoErrors.map((message) => (
                        <p key={message} className="mt-1 text-sm text-red-700">
                            {message}
                        </p>
                    ))}
                </div>

                <div className="flex flex-col gap-4 border-t border-stone-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-stone-500 sm:max-w-sm">
                        Simulated property: {describeProfile(profile)}.
                        {extractorNote(extractor)}
                    </p>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className={`${buttonPrimary} w-full py-2.5 sm:w-auto`}
                    >
                        {form.processing
                            ? 'Analyzing photos…'
                            : 'Analyze my yard'}
                    </button>
                </div>
            </form>
        </Layout>
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
