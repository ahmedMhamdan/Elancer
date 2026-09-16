import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '../discovery/shared';
import '../../../css/elancer-jobs.css';
type Project = {
    id: number;
    title: string | null;
    status: string;
    updated_at: string;
    deleted_at: string | null;
};
export default function MyProjects({
    projects,
    trash,
}: {
    projects: Page<Project>;
    trash: boolean;
}) {
    const { t } = useTranslation();
    const [pending, setPending] = useState(false);
    const [remove, setRemove] = useState<Project | null>(null);
    function create() {
        setPending(true);
        router.post('/my-projects', {}, { onFinish: () => setPending(false) });
    }
    return (
        <AppLayout
            breadcrumbs={[{ title: t('My projects'), href: '/my-projects' }]}
        >
            <Head title={t('My projects')} />
            <main className="project-editor w-full">
                <header className="project-editor-header">
                    <div>
                        <h1>{t('My projects')}</h1>
                        <p className="text-muted-foreground">
                            {t(
                                'Shape your brief, publish it, and find the right skills.',
                            )}
                        </p>
                    </div>
                    <Button
                        onClick={create}
                        disabled={pending}
                        startIcon={<Plus size={16} />}
                    >
                        {t('Post a project')}
                    </Button>
                </header>
                <nav
                    className="mb-6 flex gap-5"
                    aria-label={t('Project lists')}
                >
                    <Link
                        href="/my-projects"
                        aria-current={!trash ? 'page' : undefined}
                        className={!trash ? 'text-primary underline' : ''}
                    >
                        {t('Your projects')}
                    </Link>
                    <Link
                        href="/my-projects?status=trash"
                        aria-current={trash ? 'page' : undefined}
                        className={trash ? 'text-primary underline' : ''}
                    >
                        {t('Trash')}
                    </Link>
                    <Link href="/jobs" className="ms-auto">
                        {t('Find jobs')}
                    </Link>
                </nav>
                <div className="job-results">
                    {projects.data.map((project) => (
                        <article key={project.id} className="job-card">
                            <div className="job-card-top">
                                <h2 dir="auto">
                                    {project.title || t('Untitled project')}
                                </h2>
                                <span className="job-muted">
                                    {t(
                                        project.status === 'draft'
                                            ? 'Draft'
                                            : 'Published',
                                    )}
                                </span>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                {trash ? (
                                    <>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    `/my-projects/${project.id}/restore`,
                                                )
                                            }
                                        >
                                            {t('Restore')}
                                        </Button>
                                        <Button
                                            variant="danger-outline"
                                            onClick={() => setRemove(project)}
                                        >
                                            {t('Delete permanently')}
                                        </Button>
                                    </>
                                ) : project.status === 'draft' ? (
                                    <>
                                        <Link
                                            className="job-post-link"
                                            href={`/my-projects/${project.id}/edit`}
                                        >
                                            {t('Continue editing')}
                                        </Link>
                                        <Button
                                            variant="outline"
                                            onClick={() => setRemove(project)}
                                        >
                                            {t('Move to trash')}
                                        </Button>
                                    </>
                                ) : (
                                    <Link
                                        className="job-post-link"
                                        href={`/jobs/${project.id}`}
                                    >
                                        {t('View project')}
                                    </Link>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
                {!projects.data.length && (
                    <div className="jobs-empty">
                        <h2>
                            {t(
                                trash
                                    ? 'Your trash is empty'
                                    : 'Your next project starts here',
                            )}
                        </h2>
                        <p>
                            {t(
                                trash
                                    ? 'Deleted drafts will appear here.'
                                    : 'Create a clear brief and tell freelancers what you need.',
                            )}
                        </p>
                        {!trash && (
                            <Button onClick={create} disabled={pending}>
                                {t('Create a project')}
                            </Button>
                        )}
                    </div>
                )}
                <Pagination data={projects} />
                <Dialog
                    open={!!remove}
                    onOpenChange={(open) => {
                        if (!open) setRemove(null);
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t(
                                    trash
                                        ? 'Delete this draft permanently?'
                                        : 'Move this draft to trash?',
                                )}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    trash
                                        ? 'This permanently deletes the draft. This cannot be undone.'
                                        : 'You can restore this draft from Trash later.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex justify-end gap-3">
                            <Button
                                variant="outline"
                                onClick={() => setRemove(null)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                variant="danger"
                                disabled={pending}
                                onClick={() => {
                                    if (!remove) return;
                                    setPending(true);
                                    router.delete(
                                        `/my-projects/${remove.id}${trash ? '/permanent' : ''}`,
                                        {
                                            onSuccess: () => setRemove(null),
                                            onFinish: () => setPending(false),
                                        },
                                    );
                                }}
                            >
                                {t(
                                    trash
                                        ? 'Delete permanently'
                                        : 'Move to trash',
                                )}
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            </main>
        </AppLayout>
    );
}
