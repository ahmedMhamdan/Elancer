// Adapts TailAdmin DefaultInputs form composition using the existing licensed
// ComponentCard, InputField, TextArea, Label and Button implementations.
import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ComponentCard from '@/components/component-card';
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
    const { auth } = usePage().props;
    const client = auth.user.workspace_role === 'client';
    const form = useForm({
        headline: profile?.headline ?? '',
        bio: profile?.bio ?? '',
        country: profile?.country ?? '',
        city: profile?.city ?? '',
        company: profile?.company ?? '',
        skills: (profile?.skills ?? []).join(', '),
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            skills: data.skills
                .split(/[,،]/)
                .map((skill) => skill.trim())
                .filter(Boolean),
        }));
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
        key: 'headline' | 'country' | 'city' | 'company' | 'skills',
        label: string,
        max: number,
        hint?: string,
    ) => (
        <div>
            <Label htmlFor={`profile-${key}`}>{label}</Label>
            <Input
                id={`profile-${key}`}
                name={key}
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
                title="About you"
                desc="Give people a clear picture of who you are and what you offer."
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
                        Edit account
                    </Link>
                </div>
                {client
                    ? field(
                          'company',
                          'Company name',
                          120,
                          'Optional — leave blank if you hire as an individual.',
                      )
                    : field(
                          'headline',
                          'Professional headline',
                          120,
                          'A short description of your work and specialty.',
                      )}
                <div>
                    <Label htmlFor="profile-bio">
                        {client ? 'Your introduction' : 'About me'}
                    </Label>
                    <TextArea
                        id="profile-bio"
                        name="bio"
                        rows={6}
                        maxLength={5000}
                        value={form.data.bio}
                        onChange={(value) => form.setData('bio', value)}
                        aria-invalid={!!form.errors.bio}
                        aria-describedby="profile-bio-error"
                    />
                    <p className="text-muted-foreground mt-2 text-sm">
                        {form.data.bio.length} / 5,000 characters
                    </p>
                    <InputError
                        id="profile-bio-error"
                        message={form.errors.bio}
                    />
                </div>
            </ComponentCard>
            {!client && (
                <ComponentCard
                    title="Skills and expertise"
                    desc="Add up to 15 skills that describe your work."
                >
                    {field(
                        'skills',
                        'Skills',
                        800,
                        'Separate skills with commas. Each skill can contain up to 50 characters.',
                    )}
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
                title="Where you are based"
                desc="Share your city and country. A street address is not needed."
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    {field('country', 'Country', 100)}
                    {field('city', 'City', 100)}
                </div>
            </ComponentCard>
            <div className="border-border bg-card flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5">
                <p className="text-muted-foreground text-sm">
                    Saving updates your draft; it does not publish your profile.
                </p>
                <div className="flex items-center gap-3">
                    <span role="status" className="text-primary text-sm">
                        {form.recentlySuccessful && 'Changes saved'}
                    </span>
                    <Button
                        variant="outline"
                        disabled={form.processing || !form.isDirty}
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                        }}
                    >
                        Reset
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save changes'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
