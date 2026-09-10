import { useForm } from '@inertiajs/react';
import { CheckCircle2, ChevronDown, Circle, LoaderCircle } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ComponentCard from '@/components/component-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MarketplaceProfile } from '@/pages/dashboard';
import { update } from '@/routes/dashboard/profile';

type ProfileFields = { headline: string; bio: string; location: string };
type Field = keyof ProfileFields;

const fields: {
    key: Field;
    title: string;
    label: string;
    description: string;
    placeholder: string;
    limit: number;
}[] = [
    {
        key: 'headline',
        title: 'Introduce what you do',
        label: 'Headline',
        description: 'A clear headline helps people understand your work.',
        placeholder: 'e.g. Laravel developer for growing businesses',
        limit: 120,
    },
    {
        key: 'bio',
        title: 'Tell your story',
        label: 'Bio',
        description: 'Share your experience and what you bring to a project.',
        placeholder: 'Describe your experience and how you help your clients.',
        limit: 5000,
    },
    {
        key: 'location',
        title: 'Add your location',
        label: 'Location',
        description: 'Let people know where you are based.',
        placeholder: 'e.g. Hebron, Palestine',
        limit: 120,
    },
];

export default function ProfileForm({
    profile,
}: {
    profile: MarketplaceProfile | null;
}) {
    const [expanded, setExpanded] = useState<Field | null>(null);
    const reducedMotion = useReducedMotion();
    const form = useForm<ProfileFields>({
        headline: profile?.headline ?? '',
        bio: profile?.bio ?? '',
        location: profile?.location ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(update.url(), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (errors) => {
                const firstError = fields.find(({ key }) => errors[key]);
                if (firstError) setExpanded(firstError.key);
            },
        });
    };

    return (
        <section
            id="profile-form"
            className="profile-editor"
            aria-label="Edit your profile"
        >
            <ComponentCard
                title="Complete your profile"
                desc="Start with the essentials. Click a section to add or edit your details."
            >
                <form onSubmit={submit}>
                    <div className="profile-accordion">
                        {fields.map((field) => {
                            const open = expanded === field.key;
                            const complete = Boolean(
                                profile?.[field.key]?.trim(),
                            );
                            const error = form.errors[field.key];
                            return (
                                <div
                                    key={field.key}
                                    className="profile-accordion-item"
                                    data-open={open}
                                >
                                    <h3>
                                        <button
                                            id={`${field.key}-trigger`}
                                            type="button"
                                            className="profile-accordion-trigger"
                                            aria-expanded={open}
                                            aria-controls={`${field.key}-panel`}
                                            onClick={() =>
                                                setExpanded(
                                                    open ? null : field.key,
                                                )
                                            }
                                        >
                                            {complete ? (
                                                <CheckCircle2
                                                    className="workspace-accent"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Circle aria-hidden="true" />
                                            )}
                                            <span className="profile-accordion-copy">
                                                <strong>{field.title}</strong>
                                                <span>{field.description}</span>
                                            </span>
                                            <span className="profile-accordion-action">
                                                {open
                                                    ? 'Close'
                                                    : complete
                                                      ? 'Edit'
                                                      : 'Add'}
                                            </span>
                                            <ChevronDown
                                                className="profile-accordion-chevron"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </h3>
                                    <motion.div
                                        id={`${field.key}-panel`}
                                        role="region"
                                        aria-labelledby={`${field.key}-trigger`}
                                        aria-hidden={!open}
                                        inert={!open}
                                        initial={false}
                                        animate={{
                                            height: open ? 'auto' : 0,
                                            opacity: open ? 1 : 0,
                                        }}
                                        transition={{
                                            duration: reducedMotion ? 0 : 0.24,
                                            ease: [0.22, 1, 0.36, 1],
                                        }}
                                        className="profile-accordion-panel"
                                    >
                                        <div className="profile-accordion-input">
                                            <Label htmlFor={field.key}>
                                                {field.label}
                                            </Label>
                                            {field.key === 'bio' ? (
                                                <textarea
                                                    id={field.key}
                                                    name={field.key}
                                                    rows={5}
                                                    value={form.data[field.key]}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            field.key,
                                                            event.target.value,
                                                        )
                                                    }
                                                    maxLength={field.limit}
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                    aria-invalid={Boolean(
                                                        error,
                                                    )}
                                                    aria-describedby={`${field.key}-hint ${field.key}-error`}
                                                />
                                            ) : (
                                                <Input
                                                    id={field.key}
                                                    name={field.key}
                                                    value={form.data[field.key]}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            field.key,
                                                            event.target.value,
                                                        )
                                                    }
                                                    maxLength={field.limit}
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                    autoComplete={
                                                        field.key === 'location'
                                                            ? 'address-level2'
                                                            : 'off'
                                                    }
                                                    aria-invalid={Boolean(
                                                        error,
                                                    )}
                                                    aria-describedby={`${field.key}-hint ${field.key}-error`}
                                                />
                                            )}
                                            <div className="profile-field-meta">
                                                <p id={`${field.key}-hint`}>
                                                    {field.key === 'bio'
                                                        ? 'A short introduction is a great start.'
                                                        : 'You can update this anytime.'}
                                                </p>
                                                <span>
                                                    {
                                                        form.data[field.key]
                                                            .length
                                                    }{' '}
                                                    /{' '}
                                                    {field.limit.toLocaleString()}
                                                </span>
                                            </div>
                                            <InputError
                                                id={`${field.key}-error`}
                                                message={error}
                                            />
                                        </div>
                                    </motion.div>
                                </div>
                            );
                        })}
                    </div>
                    <div className="profile-editor-footer">
                        <p>
                            Changes are saved without publishing your profile.
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <span
                                role="status"
                                className="workspace-save-status"
                            >
                                {form.recentlySuccessful && (
                                    <>
                                        <CheckCircle2
                                            size={16}
                                            aria-hidden="true"
                                        />{' '}
                                        Saved
                                    </>
                                )}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                disabled={form.processing || !form.isDirty}
                                onClick={() => {
                                    form.reset();
                                    form.clearErrors();
                                }}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-h-11"
                                disabled={form.processing}
                            >
                                {form.processing && (
                                    <LoaderCircle
                                        className="animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                )}
                                {form.processing ? 'Saving...' : 'Save changes'}
                            </Button>
                        </div>
                    </div>
                </form>
            </ComponentCard>
        </section>
    );
}
