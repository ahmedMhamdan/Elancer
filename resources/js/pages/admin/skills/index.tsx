// Adapted from TailAdmin src/components/tables/BasicTables/BasicTableOne.tsx through the existing
// table and pagination adaptations, with the accounts page's search form and the categories page's
// status tabs and row actions. Reuses TailAdmin Alert, Input, Label and Button. MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import Pagination, {
    type PaginationData,
} from '@/components/tailadmin/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
    TableScroll,
} from '@/components/tailadmin/table';
import { useReportLabels } from '@/hooks/use-report-labels';
import { useTranslation } from '@/hooks/use-translation';

type Skill = {
    id: number;
    name: string;
    retired: boolean;
    profiles: number;
    projects: number;
};
type Change = {
    id: number;
    action:
        | 'skill_created'
        | 'skill_renamed'
        | 'skill_retired'
        | 'skill_restored';
    actor: string | null;
    skill: string | null;
    previous: string | null;
    created_at: string;
};
type Status = 'active' | 'retired' | 'all';

export default function Skills({
    skills,
    search,
    status,
    events,
    notice,
}: {
    skills: PaginationData & { data: Skill[] };
    search: string;
    status: Status;
    events: Change[];
    notice: 'created' | 'renamed' | 'retired' | 'restored' | null;
}) {
    const { t } = useTranslation();
    const { time } = useReportLabels();
    const form = useForm({ q: search, status });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const head =
        'text-muted-foreground px-4 py-3 text-start text-sm font-medium';
    const linkClass =
        'inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring';
    const tabs: Record<Status, string> = {
        active: t('Active skills'),
        retired: t('Retired skills'),
        all: t('All skills'),
    };
    const notices = {
        created: t('The skill was added.'),
        renamed: t('The skill was saved.'),
        retired: t(
            'The skill was retired. It stays on the profiles and projects that already have it.',
        ),
        restored: t('The skill was restored and can be chosen again.'),
    };
    const changes = {
        skill_created: t('Added'),
        skill_renamed: t('Renamed'),
        skill_retired: t('Retired'),
        skill_restored: t('Restored'),
    };
    const query = (value: Status) =>
        '/admin/skills?' +
        new URLSearchParams({
            ...(search ? { q: search } : {}),
            status: value,
        }).toString();
    function act(skill: Skill) {
        if (busy) return;
        if (
            !skill.retired &&
            !window.confirm(
                t(
                    'Retire :name? Members who have it keep it; nobody can newly choose it.',
                    { name: skill.name },
                ),
            )
        )
            return;
        const failed = () => {
            setError(t('The change could not be saved. Please try again.'));
            return false;
        };
        setBusy(true);
        setError('');
        router.visit(
            `/admin/skills/${skill.id}${skill.retired ? '/restore' : ''}`,
            {
                method: skill.retired ? 'post' : 'delete',
                preserveScroll: true,
                onError: failed,
                onNetworkError: failed,
                onHttpException: failed,
                onFinish: () => setBusy(false),
            },
        );
    }
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Skills')} />
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="workspace-page-heading">
                    <h1>{t('Skills')}</h1>
                    <p>
                        {t(
                            'The catalog members choose their skills from. A skill is retired, never deleted, so profiles and projects that carry it stay readable.',
                        )}
                    </p>
                </div>
                <Link className={linkClass} href="/admin/skills/create">
                    {t('Add a skill')}
                </Link>
            </div>
            {notice && <Alert message={notices[notice]} />}
            {error && <Alert variant="error" message={error} />}
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/admin/skills');
                }}
                className="flex flex-wrap items-end gap-3"
            >
                <div className="w-full max-w-md">
                    <Label htmlFor="skill-search">{t('Skill name')}</Label>
                    <Input
                        id="skill-search"
                        value={form.data.q}
                        onChange={(event) =>
                            form.setData('q', event.target.value)
                        }
                        maxLength={50}
                        dir="auto"
                    />
                </div>
                <Button type="submit" disabled={form.processing}>
                    {t('Find skill')}
                </Button>
            </form>
            <nav aria-label={t('Skills')} className="flex flex-wrap gap-2">
                {(['active', 'retired', 'all'] as const).map((value) => (
                    <Link
                        key={value}
                        href={query(value)}
                        className={`${linkClass} ${status === value ? 'bg-muted' : ''}`}
                        aria-current={status === value ? 'page' : undefined}
                    >
                        {tabs[value]}
                    </Link>
                ))}
            </nav>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <TableScroll label={t('Skills')}>
                    <Table className="w-full">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell isHeader className={head}>
                                    {t('Skill')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Profiles')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Projects')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Status')}
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="text-muted-foreground w-px px-4 py-3 text-end text-sm font-medium whitespace-nowrap"
                                >
                                    {t('Actions')}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-border divide-y">
                            {skills.data.map((skill) => (
                                <TableRow key={skill.id}>
                                    <TableCell className="px-4 py-4 text-start">
                                        <span className="block min-w-40 font-medium wrap-anywhere">
                                            <bdi>{skill.name}</bdi>
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 tabular-nums">
                                        {skill.profiles}
                                    </TableCell>
                                    <TableCell className="px-4 py-4 tabular-nums">
                                        {skill.projects}
                                    </TableCell>
                                    <TableCell className="px-4 py-4 whitespace-nowrap">
                                        {skill.retired ? (
                                            <span className="text-muted-foreground">
                                                {t('Retired')}
                                            </span>
                                        ) : (
                                            <span className="font-medium">
                                                {t('Active')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="w-px px-4 py-4 text-end">
                                        <div className="flex items-center justify-end gap-2 whitespace-nowrap">
                                            <Link
                                                href={`/admin/skills/${skill.id}/edit`}
                                                className={linkClass}
                                                aria-label={`${t('Rename')}: ${skill.name}`}
                                            >
                                                {t('Rename')}
                                            </Link>
                                            <Button
                                                size="sm"
                                                variant={
                                                    skill.retired
                                                        ? 'primary'
                                                        : 'danger-outline'
                                                }
                                                disabled={busy}
                                                onClick={() => act(skill)}
                                                aria-label={`${skill.retired ? t('Restore') : t('Retire')}: ${skill.name}`}
                                            >
                                                {skill.retired
                                                    ? t('Restore')
                                                    : t('Retire')}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!skills.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {t('No skills match.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableScroll>
            </div>
            <Pagination data={skills} />
            {events.length > 0 && (
                <section
                    aria-labelledby="skill-changes"
                    className="border-border bg-card rounded-xl border p-5"
                >
                    <h2 id="skill-changes" className="text-sm font-medium">
                        {t('Latest changes')}
                    </h2>
                    <ul className="divide-border mt-3 divide-y">
                        {events.map((event) => (
                            <li key={event.id} className="py-3 text-sm">
                                <span className="font-medium">
                                    {changes[event.action]}
                                </span>
                                {': '}
                                <bdi>{event.skill ?? t('Removed skill')}</bdi>
                                {event.previous && (
                                    <span className="text-muted-foreground">
                                        {' ('}
                                        {t('was')} <bdi>{event.previous}</bdi>
                                        {')'}
                                    </span>
                                )}
                                {' · '}
                                <bdi>{event.actor ?? t('Closed account')}</bdi>
                                {' · '}
                                <time
                                    dateTime={event.created_at}
                                    className="text-muted-foreground whitespace-nowrap"
                                >
                                    {time(event.created_at)}
                                </time>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
