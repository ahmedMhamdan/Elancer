import { useTranslation } from '@/hooks/use-translation';
import LanguageToggle from '@/components/language-toggle';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, Code2, ImagePlus, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import SkillSelect from '@/components/skill-select';
import PhotoEditor from '@/components/photo-editor';
import ElancerWordmark from '@/components/elancer-wordmark';
import ThemeToggle from '@/components/theme-toggle';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import Radio from '@/components/tailadmin/radio';
import TextArea from '@/components/tailadmin/textarea';
import { home } from '@/routes';
import '@/../css/elancer-onboarding.css';

export type OnboardingDraft = {
    role: 'freelancer' | 'client' | '';
    name: string;
    headline: string;
    bio: string;
    skills: string[];
    company: string;
    country: string;
    city: string;
    photo: File | null;
};

type OnboardingProps = {
    initial?: Partial<Omit<OnboardingDraft, 'photo'>>;
    submitUrl: string;
};

const steps = [
    'Choose your role',
    'Introduce yourself',
    'Your location',
    'Review',
];
const fieldSteps: Record<string, number> = {
    role: 0,
    name: 1,
    headline: 1,
    bio: 1,
    skills: 1,
    company: 1,
    country: 2,
    city: 2,
    photo: 2,
};

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="onboarding-field">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {error && (
                <p
                    id={id + '-error'}
                    className="text-destructive mt-2 text-sm"
                    role="alert"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

export default function Onboarding({ initial, submitUrl }: OnboardingProps) {
    const { t } = useTranslation();

    const page = usePage();
    const { auth } = page.props;
    const form = useForm<OnboardingDraft>({
        role: '',
        name: auth.user.name,
        headline: '',
        bio: '',
        skills: [],
        company: '',
        country: '',
        city: '',
        ...initial,
        photo: null,
    });
    const [step, setStep] = useState(0);
    const [photoUrl, setPhotoUrl] = useState<string>();
    const [photoError, setPhotoError] = useState('');
    const [photoSource, setPhotoSource] = useState<File | null>(null);
    const [pendingPhoto, setPendingPhoto] = useState<File | null>(null);
    const [editingPhoto, setEditingPhoto] = useState(false);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const isFreelancer = form.data.role === 'freelancer';
    const skills = form.data.skills;
    const firstName = form.data.name.trim().split(/\s+/)[0] || t('friend');

    useEffect(() => {
        headingRef.current?.focus();
    }, [step]);
    useEffect(() => {
        if (!form.data.photo) return;
        const url = URL.createObjectURL(form.data.photo);
        setPhotoUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.photo]);

    function selectPhoto(file?: File) {
        setPhotoError('');
        if (!file) return;
        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
            file.size > 2 * 1024 * 1024
        ) {
            setPhotoError('Choose a JPG, PNG or WebP image smaller than 2 MB.');
            if (photoInputRef.current) photoInputRef.current.value = '';
            return;
        }
        setPendingPhoto(file);
        setEditingPhoto(true);
        if (photoInputRef.current) photoInputRef.current.value = '';
    }

    function next(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (step === 0 && !form.data.role) return;
        const requiredFields: Exclude<
            keyof OnboardingDraft,
            'photo' | 'skills'
        >[] =
            step === 1
                ? isFreelancer
                    ? ['name', 'headline', 'bio']
                    : ['name', 'bio']
                : step === 2
                  ? ['country', 'city']
                  : [];
        const blankField = requiredFields.find((key) => !form.data[key].trim());
        if (blankField) {
            form.setError(blankField, t('Please fill in this field.'));
            document.getElementById(blankField)?.focus();
            return;
        }
        if (
            step === 1 &&
            isFreelancer &&
            (skills.length === 0 ||
                skills.length > 15 ||
                skills.some((skill) => skill.length > 50))
        ) {
            form.setError(
                'skills',
                t('Choose 1–15 skills from the suggestions.'),
            );
            document.getElementById('skills')?.focus();
            return;
        }
        if (step === 2 && photoError) {
            photoInputRef.current?.focus();
            return;
        }
        form.clearErrors();
        if (step < 3) {
            setStep(step + 1);
            return;
        }
        form.transform((data) => ({
            ...data,
            headline: data.role === 'freelancer' ? data.headline.trim() : null,
            skills: data.role === 'freelancer' ? [...new Set(skills)] : [],
            company:
                data.role === 'client' ? data.company.trim() || null : null,
        }));
        form.post(submitUrl, {
            forceFormData: true,
            onError: (errors) => {
                const first = Object.keys(errors)[0]?.split('.')[0];
                for (const [key, message] of Object.entries(errors)) {
                    const field = key.split('.')[0];
                    if (field in fieldSteps)
                        form.setError(field as keyof OnboardingDraft, message);
                }
                setStep(fieldSteps[first] ?? 1);
            },
        });
    }

    function errorProps(key: keyof OnboardingDraft) {
        return {
            'aria-invalid': Boolean(form.errors[key]),
            'aria-describedby': form.errors[key] ? key + '-error' : undefined,
        };
    }

    const titles = [
        'How would you like to use Elancer?',
        isFreelancer
            ? 'Tell us about the work you do.'
            : 'Tell us a little about yourself.',
        'A few details, and you’re ready to go.',
        t('Looking good, :name.', { name: firstName }),
    ];
    const descriptions = [
        'Choose where you’d like to start. You can hire and freelance with the same account.',
        isFreelancer
            ? 'Help clients understand your skills and what you bring to their project.'
            : 'Introduce yourself so freelancers know who they’ll be working with.',
        'Add your location and a photo to put a face to your name.',
        'Take a moment to check your details before you enter your workspace.',
    ];

    return (
        <div className="onboarding-page">
            {editingPhoto && pendingPhoto && (
                <PhotoEditor
                    file={pendingPhoto}
                    onClose={() => setEditingPhoto(false)}
                    onApply={(photo) => {
                        form.setData('photo', photo);
                        setPhotoSource(pendingPhoto);
                        setPhotoError('');
                        setEditingPhoto(false);
                    }}
                />
            )}
            <Head title={t('Set up your profile')} />
            <header className="onboarding-header">
                <Link href={home()} aria-label={t('Elancer home')}>
                    <ElancerWordmark />
                </Link>
                <div className="flex items-center gap-4">
                    <LanguageToggle />
                    <ThemeToggle />
                </div>
            </header>
            <main className="onboarding-main">
                <>
                    <div className="onboarding-progress">
                        <div className="flex items-center justify-between gap-4">
                            <span>
                                {t('Step :step of :total', {
                                    step: step + 1,
                                    total: steps.length,
                                })}
                            </span>
                            <span>{t(steps[step])}</span>
                        </div>
                        <progress
                            max={steps.length}
                            value={step + 1}
                            aria-label={t('Profile setup progress')}
                        />
                    </div>
                    <div className="onboarding-heading">
                        <h1 ref={headingRef} tabIndex={-1}>
                            {t(titles[step])}
                        </h1>
                        <p>{t(descriptions[step])}</p>
                    </div>
                    <form onSubmit={next}>
                        <fieldset
                            disabled={form.processing}
                            aria-busy={form.processing}
                            className="min-w-0"
                        >
                            <legend className="sr-only">
                                {t(steps[step])}
                            </legend>
                            {step === 0 && (
                                <div className="onboarding-role-grid">
                                    <Radio
                                        id="role-freelancer"
                                        name="role"
                                        value="freelancer"
                                        checked={isFreelancer}
                                        onChange={() =>
                                            form.setData('role', 'freelancer')
                                        }
                                        className={
                                            'onboarding-role-card ' +
                                            (isFreelancer ? 'is-selected' : '')
                                        }
                                        label={
                                            <span className="onboarding-role-copy">
                                                <Code2 aria-hidden="true" />
                                                <strong>
                                                    {t('I’m a freelancer')}
                                                </strong>
                                                <span>
                                                    {t(
                                                        'Find projects and put your skills to work.',
                                                    )}
                                                </span>
                                            </span>
                                        }
                                    />
                                    <Radio
                                        id="role-client"
                                        name="role"
                                        value="client"
                                        checked={form.data.role === 'client'}
                                        onChange={() =>
                                            form.setData('role', 'client')
                                        }
                                        className={
                                            'onboarding-role-card ' +
                                            (form.data.role === 'client'
                                                ? 'is-selected'
                                                : '')
                                        }
                                        label={
                                            <span className="onboarding-role-copy">
                                                <BriefcaseBusiness aria-hidden="true" />
                                                <strong>
                                                    {t('I’m a client')}
                                                </strong>
                                                <span>
                                                    {t(
                                                        'Find the right people to bring your ideas to life.',
                                                    )}
                                                </span>
                                            </span>
                                        }
                                    />
                                    {form.errors.role && (
                                        <p
                                            role="alert"
                                            className="text-destructive"
                                        >
                                            {form.errors.role}
                                        </p>
                                    )}
                                </div>
                            )}
                            {step === 1 && (
                                <div className="onboarding-details">
                                    <Field
                                        id="name"
                                        label={t('Your name *')}
                                        error={form.errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            maxLength={255}
                                            autoComplete="name"
                                            value={form.data.name}
                                            onChange={(event) =>
                                                form.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            {...errorProps('name')}
                                        />
                                    </Field>
                                    {isFreelancer ? (
                                        <>
                                            <Field
                                                id="headline"
                                                label={t(
                                                    'Professional headline *',
                                                )}
                                                error={form.errors.headline}
                                            >
                                                <Input
                                                    id="headline"
                                                    name="headline"
                                                    required
                                                    maxLength={120}
                                                    placeholder={t(
                                                        'e.g. Laravel developer for growing businesses',
                                                    )}
                                                    value={form.data.headline}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'headline',
                                                            event.target.value,
                                                        )
                                                    }
                                                    {...errorProps('headline')}
                                                />
                                            </Field>
                                            <Field
                                                id="skills"
                                                label={t('Your skills *')}
                                            >
                                                <SkillSelect
                                                    id="skills"
                                                    error={form.errors.skills}
                                                    value={form.data.skills}
                                                    onChange={(skills) => {
                                                        form.setData(
                                                            'skills',
                                                            skills,
                                                        );
                                                        form.clearErrors(
                                                            'skills',
                                                        );
                                                    }}
                                                    disabled={form.processing}
                                                />
                                            </Field>
                                        </>
                                    ) : (
                                        <Field
                                            id="company"
                                            label={t('Company name (optional)')}
                                            error={form.errors.company}
                                        >
                                            <Input
                                                id="company"
                                                name="company"
                                                maxLength={120}
                                                autoComplete="organization"
                                                placeholder={t(
                                                    'Your company or business',
                                                )}
                                                value={form.data.company}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'company',
                                                        event.target.value,
                                                    )
                                                }
                                                {...errorProps('company')}
                                            />
                                        </Field>
                                    )}
                                    <Field
                                        id="bio"
                                        label={
                                            isFreelancer
                                                ? t('Your introduction *')
                                                : t(
                                                      'About you or your company *',
                                                  )
                                        }
                                        error={form.errors.bio}
                                    >
                                        <TextArea
                                            id="bio"
                                            name="bio"
                                            required
                                            maxLength={5000}
                                            rows={5}
                                            placeholder={
                                                isFreelancer
                                                    ? t(
                                                          'Describe your experience and how you help clients.',
                                                      )
                                                    : t(
                                                          'Tell freelancers about your business and the work you have in mind.',
                                                      )
                                            }
                                            value={form.data.bio}
                                            onChange={(value) =>
                                                form.setData('bio', value)
                                            }
                                            {...errorProps('bio')}
                                        />
                                    </Field>
                                </div>
                            )}
                            {step === 2 && (
                                <div className="onboarding-location-layout">
                                    <div className="onboarding-photo">
                                        <div className="onboarding-avatar">
                                            {photoUrl ? (
                                                <img
                                                    src={photoUrl}
                                                    alt={t(
                                                        'Your selected profile photo',
                                                    )}
                                                    onError={() => {
                                                        setPhotoError(
                                                            'This image could not be opened. Choose another photo.',
                                                        );
                                                        form.setData(
                                                            'photo',
                                                            null,
                                                        );
                                                        setPhotoUrl(undefined);
                                                    }}
                                                />
                                            ) : (
                                                <UserRound aria-hidden="true" />
                                            )}
                                        </div>
                                        <label
                                            className="onboarding-photo-upload"
                                            htmlFor="photo"
                                        >
                                            <ImagePlus
                                                size={18}
                                                aria-hidden="true"
                                            />
                                            {photoUrl
                                                ? t('Change photo')
                                                : t('Upload photo')}
                                        </label>
                                        <input
                                            ref={photoInputRef}
                                            className="onboarding-file-input"
                                            id="photo"
                                            name="photo"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(event) =>
                                                selectPhoto(
                                                    event.target.files?.[0],
                                                )
                                            }
                                            aria-describedby="photo-help photo-error"
                                            aria-invalid={Boolean(
                                                photoError || form.errors.photo,
                                            )}
                                        />
                                        <p
                                            id="photo-help"
                                            className="onboarding-hint"
                                        >
                                            {t('Optional · JPG, PNG or WebP')}
                                            <br />
                                            {t('Up to 2 MB')}
                                            <br />
                                            {t(
                                                'Your cropped photo is sent to Sightengine for a workplace-safe content check before saving.',
                                            )}
                                        </p>
                                        {photoUrl && photoSource && (
                                            <button
                                                type="button"
                                                className="onboarding-text-button"
                                                onClick={() => {
                                                    setPendingPhoto(
                                                        photoSource,
                                                    );
                                                    setEditingPhoto(true);
                                                }}
                                            >
                                                {t('Edit photo')}
                                            </button>
                                        )}
                                        {photoUrl && (
                                            <button
                                                type="button"
                                                className="onboarding-text-button"
                                                onClick={() => {
                                                    form.setData('photo', null);
                                                    setPhotoSource(null);
                                                    setPhotoUrl(undefined);
                                                    setPhotoError('');
                                                    if (photoInputRef.current)
                                                        photoInputRef.current.value =
                                                            '';
                                                }}
                                            >
                                                {t('Remove photo')}
                                            </button>
                                        )}
                                        <p
                                            id="photo-error"
                                            role={
                                                photoError || form.errors.photo
                                                    ? 'alert'
                                                    : undefined
                                            }
                                            className="text-destructive text-sm"
                                        >
                                            {photoError || form.errors.photo}
                                        </p>
                                    </div>
                                    <div className="onboarding-location-fields">
                                        <Field
                                            id="country"
                                            label={t('Country or territory *')}
                                            error={form.errors.country}
                                        >
                                            <Input
                                                id="country"
                                                name="country"
                                                required
                                                maxLength={100}
                                                autoComplete="country-name"
                                                placeholder={t(
                                                    'e.g. Palestine',
                                                )}
                                                value={form.data.country}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'country',
                                                        event.target.value,
                                                    )
                                                }
                                                {...errorProps('country')}
                                            />
                                        </Field>
                                        <Field
                                            id="city"
                                            label={t('City *')}
                                            error={form.errors.city}
                                        >
                                            <Input
                                                id="city"
                                                name="city"
                                                required
                                                maxLength={100}
                                                autoComplete="address-level2"
                                                placeholder={t('e.g. Hebron')}
                                                value={form.data.city}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'city',
                                                        event.target.value,
                                                    )
                                                }
                                                {...errorProps('city')}
                                            />
                                        </Field>
                                        <p className="onboarding-location-note">
                                            {t(
                                                'Your city and country help people know where you’re based.',
                                            )}
                                        </p>
                                    </div>
                                </div>
                            )}
                            {step === 3 && (
                                <section
                                    className="onboarding-review"
                                    aria-label={t('Review your profile')}
                                >
                                    <div className="onboarding-review-person">
                                        <div className="onboarding-avatar">
                                            {photoUrl ? (
                                                <img src={photoUrl} alt="" />
                                            ) : (
                                                <UserRound aria-hidden="true" />
                                            )}
                                        </div>
                                        <div>
                                            <span className="onboarding-role-badge">
                                                {isFreelancer
                                                    ? t('Freelancer')
                                                    : t('Client')}
                                            </span>
                                            <h2>{form.data.name}</h2>
                                            <p>
                                                {form.data.city},{' '}
                                                {form.data.country}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            className="onboarding-text-button"
                                            onClick={() => setStep(2)}
                                        >
                                            {t('Edit photo & location')}
                                        </button>
                                    </div>
                                    <div className="onboarding-review-body">
                                        {isFreelancer ? (
                                            <h3>{form.data.headline}</h3>
                                        ) : (
                                            form.data.company && (
                                                <h3>{form.data.company}</h3>
                                            )
                                        )}
                                        <p className="whitespace-pre-wrap">
                                            {form.data.bio}
                                        </p>
                                        {isFreelancer && (
                                            <ul
                                                className="onboarding-skills"
                                                aria-label={t('Skills')}
                                            >
                                                {[...new Set(skills)].map(
                                                    (skill) => (
                                                        <li key={skill}>
                                                            {skill}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                        <button
                                            type="button"
                                            className="onboarding-text-button"
                                            onClick={() => setStep(1)}
                                        >
                                            {t('Edit introduction')}
                                        </button>
                                    </div>
                                </section>
                            )}
                            <footer className="onboarding-footer">
                                {step > 0 ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => setStep(step - 1)}
                                    >
                                        {t('Back')}
                                    </Button>
                                ) : (
                                    <Link
                                        className="onboarding-text-button"
                                        href={home()}
                                    >
                                        {t('Back to home')}
                                    </Link>
                                )}
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (step === 0 && !form.data.role)
                                    }
                                >
                                    {form.processing
                                        ? t('Saving your profile…')
                                        : step === 3
                                          ? t('Save and continue')
                                          : step === 2
                                            ? t('Review your profile')
                                            : t('Continue')}
                                </Button>
                            </footer>
                        </fieldset>
                    </form>
                </>
            </main>
        </div>
    );
}
