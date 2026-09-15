import { useTranslation } from '@/hooks/use-translation';
// Adapts TailAdmin DefaultInputs form composition using the existing licensed
// ComponentCard, InputField, TextArea, Label and Button implementations.
import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ComponentCard from '@/components/component-card';
import SkillSelect from '@/components/skill-select';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import TextArea from '@/components/tailadmin/textarea';
import type { MarketplaceProfile } from '@/pages/marketplace-profile';

export default function ProfileForm({
    profile,
}: {
    profile: MarketplaceProfile | null;
}) {
    const { t } = useTranslation();

    const { auth } = usePage().props;
    const client = auth.user.workspace_role === 'client';
    const form = useForm({
        headline: profile?.headline ?? '',
        bio: profile?.bio ?? '',
        country: profile?.country ?? '',
        city: profile?.city ?? '',
        company: profile?.company ?? '',
        skills: profile?.skills ?? [],
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch('/my-profile', {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: () => {
                requestAnimationFrame(() =>
                    document
                        .querySelector<HTMLElement>('[aria-invalid="true"]')
                        ?.focus(),
                );
            },
        });
    }
    const field = (
        key: 'headline' | 'country' | 'city' | 'company',
        label: string,
        max: number,
        hint?: string,
    ) => (
        <div>
            <Label htmlFor={`profile-${key}`}>{label}</Label>
            <Input
                id={`profile-${key}`}
                name={key}
                dir="auto"
                value={form.data[key]}
                maxLength={max}
                onChange={(e) => form.setData(key, e.target.value)}
                aria-invalid={!!form.errors[key]}
                aria-describedby={
                    form.errors[key] ? `profile-${key}-error` : undefined
                }
            />
            <InputError
                id={`profile-${key}-error`}
                message={form.errors[key]}
            />
            {hint && (
                <p className="text-muted-foreground mt-2 text-sm">{hint}</p>
            )}
        </div>
    );
    return (
        <form id="profile-form" onSubmit={submit} className="space-y-6">
            <ComponentCard
                title={t('About you')}
                desc={t(
                    'Give people a clear picture of who you are and what you offer.',
                )}
            >
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="font-medium">{auth.user.name}</p>
                        <p className="text-muted-foreground text-sm">
                            {auth.user.email}
                        </p>
                    </div>
                    <Link
                        href="/settings/profile"
                        className="text-primary min-h-11 px-3 py-3 text-sm underline"
                    >
                        {t('Edit account')}
                    </Link>
                </div>
                {client
                    ? field(
                          'company',
                          t('Company name'),
                          120,
                          t(
                              'Optional — leave blank if you hire as an individual.',
                          ),
                      )
                    : field(
                          'headline',
                          t('Professional headline'),
                          120,
                          t('A short description of your work and specialty.'),
                      )}
                <div>
                    <Label htmlFor="profile-bio">
                        {client ? t('Your introduction') : t('About me')}
                    </Label>
                    <TextArea
                        id="profile-bio"
                        name="bio"
                        dir="auto"
                        rows={6}
                        maxLength={5000}
                        value={form.data.bio}
                        onChange={(value) => form.setData('bio', value)}
                        aria-invalid={!!form.errors.bio}
                        aria-describedby="profile-bio-error"
                    />
                    <p className="text-muted-foreground mt-2 text-sm">
                        {t(':count / 5,000 characters', {
                            count: form.data.bio.length,
                        })}
                    </p>
                    <InputError
                        id="profile-bio-error"
                        message={form.errors.bio}
                    />
                </div>
            </ComponentCard>
            {!client && (
                <ComponentCard
                    title={t('Skills and expertise')}
                    desc={t('Add up to 15 skills that describe your work.')}
                >
                    <Label htmlFor="profile-skills">{t('Skills')}</Label>
                    <SkillSelect
                        id="profile-skills"
                        value={form.data.skills}
                        onChange={(skills) => form.setData('skills', skills)}
                        error={form.errors.skills}
                        disabled={form.processing}
                    />
                    <InputError
                        message={
                            Object.entries(form.errors).find(([key]) =>
                                key.startsWith('skills.'),
                            )?.[1]
                        }
                    />
                </ComponentCard>
            )}
            <ComponentCard
                title={t('Where you are based')}
                desc={t(
                    'Share your city and country. A street address is not needed.',
                )}
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    {field('country', t('Country'), 100)}
                    {field('city', t('City'), 100)}
                </div>
            </ComponentCard>
            <div className="border-border bg-card flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5">
                <p className="text-muted-foreground text-sm">
                    {t(
                        'Saving updates your draft; it does not publish your profile.',
                    )}
                </p>
                <div className="flex items-center gap-3">
                    <span role="status" className="text-primary text-sm">
                        {form.recentlySuccessful && t('Changes saved')}
                    </span>
                    <Button
                        variant="outline"
                        disabled={form.processing || !form.isDirty}
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                        }}
                    >
                        {t('Reset')}
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? t('Saving…') : t('Save changes')}
                    </Button>
                </div>
            </div>
        </form>
    );
}
