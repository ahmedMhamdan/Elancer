// Uses the adapted TailAdmin ComponentCard, Alert, TextArea and Button
// (MIT: THIRD_PARTY_NOTICES.md).
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import ComponentCard from '@/components/component-card';
import InputError from '@/components/input-error';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
import TextArea from '@/components/tailadmin/textarea';
import { useReportLabels } from '@/hooks/use-report-labels';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    account: {
        id: number;
        name: string;
        email: string;
        status: 'active' | 'suspended' | 'deactivated';
        is_admin: boolean;
        is_super_admin: boolean;
        email_verified_at: string | null;
        created_at: string;
        suspension_reason: string | null;
        suspended_at: string | null;
        contracts: number;
        reports: number;
    };
    events: {
        id: number;
        action: 'account_suspended' | 'account_reinstated';
        actor: string | null;
        reason: string | null;
        member_reason: string | null;
        report_id: number | null;
        created_at: string;
    }[];
    report: number | null;
    can: { suspend: boolean; reinstate: boolean };
    notice: 'suspended' | 'reinstated' | null;
};

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="wrap-anywhere">{children}</dd>
        </div>
    );
}

export default function AccountPage({
    account,
    events,
    report,
    can,
    notice,
}: Props) {
    const { t } = useTranslation();
    const { time } = useReportLabels();
    const [error, setError] = useState('');
    const form = useForm({ reason: '', member_reason: '' });
    const statuses = {
        active: t('Active'),
        suspended: t('Suspended'),
        deactivated: t('Deactivated'),
    };
    const notices = {
        suspended: t(
            'Account suspended. Their pending offers were closed and they now see the reason.',
        ),
        reinstated: t(
            'Account reinstated. Offers closed by the suspension stay closed.',
        ),
    };
    const actions = {
        account_suspended: t('Account suspended'),
        account_reinstated: t('Account reinstated'),
    };
    const ordinary = !account.is_admin && !account.is_super_admin;
    const submit = (action: 'suspend' | 'reinstate', question: string) => {
        if (!window.confirm(question)) return;
        setError('');
        form.transform((data) => ({
            ...data,
            action,
            ...(report ? { report } : {}),
        }));
        form.patch(`/admin/accounts/${account.id}`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onHttpException: () => {
                setError(
                    t(
                        'The account changed or the request failed. Reload the page.',
                    ),
                );
                return false;
            },
        });
    };
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={account.name} />
            <Link
                href={report ? `/admin/reports/${report}` : '/admin/accounts'}
                className="text-primary focus-visible:outline-ring inline-flex min-h-11 items-center gap-2 font-medium underline-offset-4 hover:underline focus-visible:outline-2"
            >
                <ArrowLeft
                    size={17}
                    className="rtl:rotate-180"
                    aria-hidden="true"
                />
                {report
                    ? t('Back to report #:id', { id: report })
                    : t('Back to accounts')}
            </Link>
            <div className="workspace-page-heading">
                <h1>
                    <bdi>{account.name}</bdi>
                </h1>
                <p>{statuses[account.status]}</p>
            </div>
            {notice && <Alert message={notices[notice]} />}
            {error && <Alert variant="error" message={error} />}
            <ComponentCard title={t('Account')}>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <Fact label={t('Email')}>
                        <bdi dir="ltr">{account.email}</bdi>
                        {!account.email_verified_at && (
                            <span className="text-muted-foreground text-sm">
                                {' · '}
                                {t('Email not verified')}
                            </span>
                        )}
                    </Fact>
                    <Fact label={t('Role')}>
                        {account.is_super_admin
                            ? t('Super administrator')
                            : account.is_admin
                              ? t('Administrator')
                              : t('Member')}
                    </Fact>
                    <Fact label={t('Joined')}>
                        <time dateTime={account.created_at}>
                            {time(account.created_at)}
                        </time>
                    </Fact>
                    <Fact label={t('Contracts in progress')}>
                        {account.contracts}
                    </Fact>
                    <Fact label={t('Reports about this member')}>
                        {account.reports}
                    </Fact>
                    {account.suspended_at && (
                        <Fact label={t('Suspended since')}>
                            <time dateTime={account.suspended_at}>
                                {time(account.suspended_at)}
                            </time>
                        </Fact>
                    )}
                </dl>
                {account.suspension_reason && (
                    <div>
                        <p className="text-muted-foreground text-sm">
                            {t('Reason shown to the member')}
                        </p>
                        <p
                            dir="auto"
                            className="wrap-anywhere whitespace-pre-wrap"
                        >
                            {account.suspension_reason}
                        </p>
                    </div>
                )}
            </ComponentCard>
            {can.suspend || can.reinstate ? (
                <ComponentCard
                    title={
                        can.suspend
                            ? t('Suspend this account')
                            : t('Reinstate this account')
                    }
                    desc={
                        can.suspend
                            ? t(
                                  'The member keeps their existing contracts and conversations. They can no longer post projects, apply, invite, send or accept offers, or fund a contract, and their pending offers are closed. No contract is completed, cancelled or refunded.',
                              )
                            : t(
                                  'The member can use the marketplace again. Offers closed by the suspension are not reopened.',
                              )
                    }
                >
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (can.suspend)
                                submit(
                                    'suspend',
                                    t(
                                        'Suspend this account? The member will see the reason you wrote for them.',
                                    ),
                                );
                            else
                                submit(
                                    'reinstate',
                                    t('Reinstate this account?'),
                                );
                        }}
                    >
                        {can.suspend && (
                            <div>
                                <Label htmlFor="member-reason">
                                    {t('Reason shown to the member')}
                                </Label>
                                <TextArea
                                    id="member-reason"
                                    rows={3}
                                    required
                                    minLength={10}
                                    maxLength={500}
                                    dir="auto"
                                    placeholder=""
                                    value={form.data.member_reason}
                                    onChange={(value) =>
                                        form.setData('member_reason', value)
                                    }
                                />
                                <InputError
                                    message={form.errors.member_reason}
                                />
                            </div>
                        )}
                        <div>
                            <Label htmlFor="internal-reason">
                                {t('Internal reason (kept in the audit log)')}
                            </Label>
                            <TextArea
                                id="internal-reason"
                                rows={3}
                                required
                                minLength={5}
                                maxLength={1000}
                                dir="auto"
                                placeholder=""
                                value={form.data.reason}
                                onChange={(value) =>
                                    form.setData('reason', value)
                                }
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                        {report && (
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'This will be recorded against report #:id.',
                                    { id: report },
                                )}
                            </p>
                        )}
                        <Button
                            type="submit"
                            variant={can.suspend ? 'danger' : 'primary'}
                            disabled={
                                form.processing ||
                                form.data.reason.trim().length < 5 ||
                                (can.suspend &&
                                    form.data.member_reason.trim().length < 10)
                            }
                        >
                            {can.suspend
                                ? t('Suspend account')
                                : t('Reinstate account')}
                        </Button>
                    </form>
                </ComponentCard>
            ) : (
                <ComponentCard title={t('Suspension')}>
                    <p className="text-muted-foreground">
                        {ordinary
                            ? t(
                                  'This account is deactivated, so there is nothing to suspend.',
                              )
                            : t(
                                  'Administrator accounts cannot be suspended or reinstated here.',
                              )}
                    </p>
                </ComponentCard>
            )}
            <ComponentCard title={t('Status history')}>
                {events.length ? (
                    <ol className="space-y-4">
                        {events.map((event) => (
                            <li key={event.id} className="space-y-1">
                                <p>
                                    <span className="font-medium">
                                        {actions[event.action]}
                                    </span>
                                    {' · '}
                                    <bdi>
                                        {event.actor ?? t('Closed account')}
                                    </bdi>
                                    {' · '}
                                    <time
                                        dateTime={event.created_at}
                                        className="text-muted-foreground text-sm whitespace-nowrap"
                                    >
                                        {time(event.created_at)}
                                    </time>
                                </p>
                                {event.member_reason && (
                                    <p className="text-sm wrap-anywhere">
                                        <span className="text-muted-foreground">
                                            {t('Shown to the member:')}
                                        </span>{' '}
                                        <bdi>{event.member_reason}</bdi>
                                    </p>
                                )}
                                {event.reason && (
                                    <p className="text-sm wrap-anywhere">
                                        <span className="text-muted-foreground">
                                            {t('Internal:')}
                                        </span>{' '}
                                        <bdi>{event.reason}</bdi>
                                    </p>
                                )}
                                {event.report_id && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('Report #:id', {
                                            id: event.report_id,
                                        })}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-muted-foreground">
                        {t('This account has never been suspended.')}
                    </p>
                )}
            </ComponentCard>
        </div>
    );
}
