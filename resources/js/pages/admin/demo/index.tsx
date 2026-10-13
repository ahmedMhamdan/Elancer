// Follows admin/operations: the bordered figure cards of TailAdmin
// src/components/ecommerce/EcommerceMetrics.tsx that Finance already adapts, above a table adapted
// from src/components/tables/BasicTables/BasicTableOne.tsx. Reuses TailAdmin Alert and Button.
// MIT: THIRD_PARTY_NOTICES.md.
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
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

type Baseline = {
    taken_at: string;
    taken_by: string | null;
    accounts: number;
    rows: number;
    changed_at: string | null;
    restored_at: string | null;
    problem: string | null;
};

type Activity = {
    id: number;
    action: 'demo_saved' | 'demo_reset';
    actor: string | null;
    details: Record<string, number | boolean>;
    created_at: string;
};

/** Plan 027: the demo accounts' starting point and the reset to it, for super administrators. */
export default function DemoData({
    accounts,
    entry,
    actions,
    rules,
    baseline,
    events,
    notice,
}: {
    accounts: number;
    entry: string[];
    actions: boolean;
    rules: { idle_minutes: number; max_hours: number };
    baseline: Baseline | null;
    events: Activity[];
    notice: string | null;
}) {
    const { t } = useTranslation();
    const { time } = useReportLabels();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const head =
        'text-muted-foreground px-4 py-3 text-start text-sm font-medium';
    // A refusal arrives as a short code with the tables concerned.
    function reason(code: string) {
        const [kind, first = '', second = ''] = code.split('|');
        if (kind === 'none') return t('No starting point has been saved yet.');
        if (kind === 'structure')
            return t(
                'The saved starting point is older than the current structure of the table :table. Save a new starting point.',
                { table: first },
            );
        if (kind === 'dependents')
            return t(
                'A row outside the demo depends on a demo row that would be deleted (:parent, through :child), so nothing was reset.',
                { parent: first, child: second },
            );
        return t(
            'The saved starting point does not match the demo accounts in this database, so nothing was written.',
        );
    }
    function act(save: boolean) {
        if (busy) return;
        const question = save
            ? t(
                  'Save the demo accounts as they are now as the starting point? Everything they hold now becomes public and is what every reset returns to.',
              )
            : t(
                  'Reset the demo now? Everything demo visitors did since the starting point is removed, including for anyone using the demo right now.',
              );
        if (!window.confirm(question)) return;
        const failed = () => {
            setError(t('The change could not be saved. Please try again.'));
            return false;
        };
        setBusy(true);
        setError('');
        router.post(
            save ? '/admin/demo/baseline' : '/admin/demo/reset',
            {},
            {
                preserveScroll: true,
                onError: failed,
                onNetworkError: failed,
                onHttpException: failed,
                onFinish: () => setBusy(false),
            },
        );
    }
    const refused = notice?.startsWith('refused|')
        ? notice.slice('refused|'.length)
        : null;
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Demo data')} />
            <div className="workspace-page-heading">
                <h1>{t('Demo data')}</h1>
                <p>
                    {t(
                        'Visitors enter the demo accounts from the login and register pages. Once a starting point is saved they can act among demo accounts, and a reset returns the demo accounts to it. Nothing here writes to a member’s account or to anything a member owns.',
                    )}
                </p>
            </div>
            {notice === 'saved' && (
                <Alert
                    message={t(
                        'The demo accounts as they are now are the starting point.',
                    )}
                />
            )}
            {notice === 'reset' && (
                <Alert
                    message={t('The demo was reset to its starting point.')}
                />
            )}
            {refused && <Alert variant="error" message={reason(refused)} />}
            {error && <Alert variant="error" message={error} />}
            {!refused && baseline?.problem && (
                <Alert
                    variant="error"
                    message={`${t('The last reset was refused.')} ${reason(baseline.problem)}`}
                />
            )}
            <dl className="grid gap-4 lg:grid-cols-3">
                <div className="border-border bg-card rounded-xl border p-5">
                    <dt className="text-muted-foreground text-sm">
                        {t('Starting point')}
                    </dt>
                    <dd className="mt-2 text-lg font-semibold">
                        {baseline ? (
                            <time dateTime={baseline.taken_at}>
                                {time(baseline.taken_at)}
                            </time>
                        ) : (
                            t('Not saved')
                        )}
                    </dd>
                    <dd className="text-muted-foreground mt-1 text-sm">
                        {baseline
                            ? `${t(':accounts demo accounts, :rows rows', { accounts: baseline.accounts, rows: baseline.rows })}${baseline.taken_by ? ` · ${t('Saved by :name', { name: baseline.taken_by })}` : ''}`
                            : t(
                                  'Until one is saved, demo visitors can only look.',
                              )}
                    </dd>
                </div>
                <div className="border-border bg-card rounded-xl border p-5">
                    <dt className="text-muted-foreground text-sm">
                        {t('Demo visitors')}
                    </dt>
                    <dd className="mt-2 text-lg font-semibold">
                        {entry.length === 0
                            ? t('Cannot enter')
                            : baseline && actions
                              ? t('Can act')
                              : t('Can only look')}
                    </dd>
                    <dd className="text-muted-foreground mt-1 text-sm">
                        {baseline?.changed_at ? (
                            <>
                                {t('Last change')}{' '}
                                <time dateTime={baseline.changed_at}>
                                    {time(baseline.changed_at)}
                                </time>
                            </>
                        ) : baseline ? (
                            t(
                                ':count demo accounts in this database. Nothing has changed since the starting point.',
                                { count: accounts },
                            )
                        ) : (
                            t(':count demo accounts in this database.', {
                                count: accounts,
                            })
                        )}
                    </dd>
                </div>
                <div className="border-border bg-card rounded-xl border p-5">
                    <dt className="text-muted-foreground text-sm">
                        {t('Last reset')}
                    </dt>
                    <dd className="mt-2 text-lg font-semibold">
                        {baseline?.restored_at ? (
                            <time dateTime={baseline.restored_at}>
                                {time(baseline.restored_at)}
                            </time>
                        ) : (
                            t('Never')
                        )}
                    </dd>
                    <dd className="text-muted-foreground mt-1 text-sm">
                        {t(
                            'A reset runs by itself when a visitor enters a demo that nobody has changed for :minutes minutes, or that has stayed changed for :hours hours.',
                            {
                                minutes: rules.idle_minutes,
                                hours: rules.max_hours,
                            },
                        )}
                    </dd>
                </div>
            </dl>
            <div className="flex flex-wrap gap-3">
                <Button
                    size="md"
                    disabled={busy || accounts === 0}
                    onClick={() => act(true)}
                >
                    {t('Save the current state as the starting point')}
                </Button>
                <Button
                    size="md"
                    variant="danger-outline"
                    disabled={busy || !baseline}
                    onClick={() => act(false)}
                >
                    {t('Reset the demo now')}
                </Button>
            </div>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <TableScroll label={t('Latest activity')}>
                    <Table className="w-full">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell isHeader className={head}>
                                    {t('When')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('What happened')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('By')}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-border divide-y">
                            {events.map((event) => (
                                <TableRow key={event.id}>
                                    <TableCell className="px-4 py-4 text-start whitespace-nowrap">
                                        <time dateTime={event.created_at}>
                                            {time(event.created_at)}
                                        </time>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 text-start">
                                        <span className="block min-w-56">
                                            {event.action === 'demo_saved'
                                                ? t(
                                                      'Starting point saved: :rows rows of :accounts demo accounts',
                                                      {
                                                          rows: Number(
                                                              event.details
                                                                  .rows ?? 0,
                                                          ),
                                                          accounts: Number(
                                                              event.details
                                                                  .accounts ??
                                                                  0,
                                                          ),
                                                      },
                                                  )
                                                : t(
                                                      'Reset: :removed rows removed, :returned brought back, :restored set back',
                                                      {
                                                          removed: Number(
                                                              event.details
                                                                  .removed ?? 0,
                                                          ),
                                                          returned: Number(
                                                              event.details
                                                                  .returned ??
                                                                  0,
                                                          ),
                                                          restored: Number(
                                                              event.details
                                                                  .restored ??
                                                                  0,
                                                          ),
                                                      },
                                                  )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 text-start">
                                        {event.actor ??
                                            (event.details.automatic
                                                ? t(
                                                      'By itself, when a visitor entered',
                                                  )
                                                : t('From the command line'))}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!events.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {t('Nothing has happened yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableScroll>
            </div>
        </div>
    );
}
