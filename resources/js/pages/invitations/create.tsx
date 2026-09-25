// Form composition follows local TailAdmin components/form/form-elements/DefaultInputs.tsx.
import { Link, useForm } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
import Pagination from '@/components/tailadmin/pagination';
import InputError from '@/components/input-error';
import type { Freelancer } from '@/components/freelancer-card';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '@/pages/discovery/shared';
import { InvitationLayout } from './shared';

export default function Create({
    freelancer,
    projects,
    blocked,
}: {
    freelancer: Freelancer;
    projects: Page<{ id: number; title: string }>;
    blocked: boolean;
}) {
    const { t } = useTranslation();
    const form = useForm({ project_id: '' });
    return (
        <InvitationLayout>
            <section className="market-panel market-stack">
                <h2>{t('Invite to a project')}</h2>
                <p dir="auto">{freelancer.name}</p>
                {blocked ? (
                    <p>
                        {t(
                            'Invitations are unavailable between these accounts.',
                        )}
                    </p>
                ) : projects.data.length ? (
                    <form
                        className="market-stack"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(`/freelancers/${freelancer.id}/invite`);
                        }}
                    >
                        <div>
                            <Label htmlFor="invitation-project">
                                {t('Choose an open project')}
                            </Label>
                            <select
                                id="invitation-project"
                                className="h-11 w-full rounded-lg border border-[var(--el-border)] bg-[var(--el-surface)] px-3 text-[var(--el-text)]"
                                required
                                value={form.data.project_id}
                                onChange={(event) =>
                                    form.setData(
                                        'project_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">
                                    {t('Choose an open project')}
                                </option>
                                {projects.data.map((project) => (
                                    <option key={project.id} value={project.id}>
                                        {project.title}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.project_id} />
                        </div>
                        <p className="market-muted">
                            {t(
                                'An invitation is accepted only when a proposal is submitted.',
                            )}
                        </p>
                        <div>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing || !form.data.project_id
                                }
                            >
                                {t('Send invitation')}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p>
                        {t(
                            'Publish an open project before inviting freelancers.',
                        )}
                    </p>
                )}
                <Pagination data={projects} />
                <Link href="/my-projects">{t('My projects')}</Link>
            </section>
        </InvitationLayout>
    );
}
