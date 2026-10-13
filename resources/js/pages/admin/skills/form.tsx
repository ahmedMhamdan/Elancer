// Adapted from TailAdmin src/components/form/form-elements/DefaultInputs.tsx, as the category form is:
// ComponentCard > space-y-6 > Label/Input, with Inertia validation and accessible error associations.
// MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ComponentCard from '@/components/component-card';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import { useTranslation } from '@/hooks/use-translation';

export default function SkillForm({
    skill,
}: {
    skill: { id: number; name: string } | null;
}) {
    const { t } = useTranslation();
    const form = useForm({ name: skill?.name ?? '' });
    const [requestError, setRequestError] = useState('');
    useEffect(() => {
        if (form.errors.name) document.getElementById('skill-name')?.focus();
    }, [form.errors.name]);
    const title = skill ? t('Rename skill') : t('Add a skill');
    const failed = () => {
        setRequestError(t('The change could not be saved. Please try again.'));
        return false;
    };
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={title} />
            <Link
                href="/admin/skills"
                className="text-primary inline-flex min-h-11 items-center underline"
            >
                {t('Back to skills')}
            </Link>
            <div className="workspace-page-heading">
                <h1>{title}</h1>
            </div>
            <div className="max-w-2xl">
                <ComponentCard title={title}>
                    <form
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            setRequestError('');
                            const options = {
                                onNetworkError: failed,
                                onHttpException: failed,
                            };
                            if (skill)
                                form.put(`/admin/skills/${skill.id}`, options);
                            else form.post('/admin/skills', options);
                        }}
                    >
                        {requestError && (
                            <p role="alert" className="text-[var(--el-error)]">
                                {requestError}
                            </p>
                        )}
                        <div>
                            <Label htmlFor="skill-name">
                                {t('Skill name')}
                            </Label>
                            <Input
                                id="skill-name"
                                name="name"
                                required
                                minLength={2}
                                maxLength={50}
                                dir="auto"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                error={Boolean(form.errors.name)}
                                style={
                                    form.errors.name
                                        ? { borderColor: 'var(--el-error)' }
                                        : undefined
                                }
                                aria-invalid={Boolean(form.errors.name)}
                                aria-describedby="skill-name-hint skill-name-error"
                            />
                            <p
                                id="skill-name-hint"
                                className="text-muted-foreground mt-2 text-sm"
                            >
                                {skill
                                    ? t(
                                          'Every profile and project that carries this skill shows the new name at once. Approved case studies and sent proposals keep the name they were written with.',
                                      )
                                    : t(
                                          'Between 2 and 50 characters, in English or Arabic. Members can choose it as soon as it is added.',
                                      )}
                            </p>
                            <p
                                id="skill-name-error"
                                role={form.errors.name ? 'alert' : undefined}
                                className="mt-2 text-sm text-[var(--el-error)]"
                            >
                                {form.errors.name}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? t('Saving...')
                                    : skill
                                      ? t('Save name')
                                      : t('Add skill')}
                            </Button>
                            <Link
                                className="text-primary inline-flex min-h-11 items-center underline"
                                href="/admin/skills"
                            >
                                {t('Cancel')}
                            </Link>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </div>
    );
}
