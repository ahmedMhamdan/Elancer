'use client';

// Adapted from Ahmed's supplied shadcnstore App Dashboard Layout.
// Local TailAdmin EcommerceMetrics, StatisticsChart and RecentOrders were inspected:
// retain their metric icon blocks, chart heading and compact linked rows with Elancer tokens.
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Check, ChevronRight, FileText, Mail, MessageSquare, ShieldCheck, Signature } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { ActivityChart } from '@/components/ui/app-1-utils/activity-chart';
import { useTranslation } from '@/hooks/use-translation';
import type { OverviewData } from '@/types/overview';
import '@/../css/elancer-overview.css';

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft', published: 'Published', closed: 'Closed', hired: 'Hired',
    submitted: 'Submitted', withdrawn: 'Withdrawn', declined: 'Declined', reopened: 'Reopened',
    awaiting_payment: 'Awaiting payment', active: 'Funded and active',
    revision_requested: 'Revision requested', completed: 'Completed',
    cancellation_pending: 'Cancellation pending', cancelled: 'Cancelled',
};
const CHECK_LABELS = { headline: 'Headline', bio: 'Bio', skills: 'Skills', location: 'Location' };

export function App1({ overview, published }: { overview: OverviewData; published: boolean }) {
    const { auth } = usePage().props;
    const { t, locale, ar } = useTranslation();
    const { client, counts } = overview;
    const number = (value: number) => value.toLocaleString(locale);
    const date = (value: string) => new Date(value).toLocaleDateString(locale, { month: 'short', day: 'numeric' });
    const stats = [
        client
            ? { label: 'Your projects', value: counts.projects, hint: 'Owned projects, excluding trash', href: '/my-projects', Icon: FileText }
            : { label: 'Your proposals', value: counts.proposals, hint: 'Draft and submitted applications', href: '/my-proposals', Icon: FileText },
        client
            ? { label: 'Received proposals', value: counts.proposals, hint: 'Submitted applications to your projects', href: '/my-projects', Icon: Mail }
            : { label: 'Pending invitations', value: counts.invitations, hint: 'Invitations awaiting your response', href: '/invitations', Icon: Mail },
        { label: 'Contracts', value: counts.contracts, hint: t(':count awaiting payment', { count: counts.awaiting_payment }), href: '/contracts', Icon: Signature },
        { label: 'Unread messages', value: counts.unread, hint: 'Messages from your conversations', href: '/messages?unread=1', Icon: MessageSquare },
    ];
    const checks = Object.entries(overview.profile_checks) as [keyof typeof CHECK_LABELS, boolean][];
    const complete = checks.filter(([, ready]) => ready).length;
    const readiness = Math.round(complete / Math.max(1, checks.length) * 100);
    const allWork = [...overview.agreements, ...overview.work];
    return (
        <div className="overview-content flex min-w-0 flex-col gap-6">
            <div className="overview-heading">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{t('Overview')}</h1>
                    <p className="text-muted-foreground mt-2 text-sm">{t('Welcome back, :name. Here is your workspace at a glance.', { name: auth.user.name.trim().split(/\s+/u)[0] })}</p>
                </div>
            </div>

            <div className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map(({ label, value, hint, href, Icon }) => (
                    <Card key={label} className="overview-stat overview-card">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle><h2 className="text-sm font-medium">{t(label)}</h2></CardTitle>
                            <span className="overview-stat-icon"><Icon className="size-5" aria-hidden="true" /></span>
                        </CardHeader>
                        <CardContent>
                            <Link href={href} className="overview-stat-link">
                                <span className="text-3xl font-semibold tabular-nums">{number(value)}</span>
                                <ArrowUpRight className={ar ? 'size-4 -scale-x-100' : 'size-4'} aria-hidden="true" />
                                <span className="sr-only">{t(label)}</span>
                            </Link>
                            <p className="text-muted-foreground mt-2 text-xs leading-relaxed">{t(hint)}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                <ActivityChart data={overview.chart} client={client} />
                <Card className="overview-card">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <CardTitle><h2>{t('Profile readiness')}</h2></CardTitle>
                            <Badge variant="outline">{client ? t('Client profile') : published ? t('Published') : t('Draft')}</Badge>
                        </div>
                        <CardDescription>{client ? t('Help freelancers get to know you.') : t('Prepare your profile for the marketplace.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-1 flex-col gap-4">
                        <div>
                            <div className="mb-2 flex items-center justify-between text-sm"><span>{t(':count of :total profile checks', { count: complete, total: checks.length })}</span><span className="font-semibold tabular-nums">{number(readiness)}%</span></div>
                            <Progress value={readiness} aria-label={t('Profile readiness')} />
                        </div>
                        <ul className="grid gap-2">
                            {checks.map(([key, ready]) => <li key={key} className="flex items-center gap-2 text-sm"><span className={ready ? 'overview-check overview-check-ready' : 'overview-check'}>{ready && <Check className="size-3" aria-hidden="true" />}</span>{t(CHECK_LABELS[key])}<span className="text-muted-foreground ms-auto text-xs">{ready ? t('Ready') : t('To do')}</span></li>)}
                        </ul>
                        <Link href="/my-profile" className="overview-text-link min-h-11">{t('Manage profile')}<ChevronRight className={ar ? 'size-4 rotate-180' : 'size-4'} aria-hidden="true" /></Link>
                        <div className="overview-account-links">
                            <Link href="/settings/security"><ShieldCheck className="size-4" aria-hidden="true" /><span>{auth.user.two_factor_enabled ? t('2FA enabled') : t('Set up 2FA')}</span><ChevronRight className={ar ? 'ms-auto size-4 rotate-180' : 'ms-auto size-4'} aria-hidden="true" /></Link>
                            <Link href="/settings/profile">{t('Check your account details')}<ChevronRight className={ar ? 'ms-auto size-4 rotate-180' : 'ms-auto size-4'} aria-hidden="true" /></Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card className="overview-card">
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle><h2>{t('Finance')}</h2></CardTitle>
                        <Link href="/finance" className="overview-text-link">{t('Open finance')}<ChevronRight className={ar ? 'size-4 rotate-180' : 'size-4'} aria-hidden="true" /></Link>
                    </div>
                    <CardDescription>{t('Agreed contract amounts in test mode. No real money moves.')}</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    {([['awaiting', client ? 'Awaiting your funding' : 'Awaiting client funding'], ['funded', client ? 'Funded by you' : 'Funded for your work']] as const).map(([key, label]) => (
                        <div key={key}>
                            <p className="text-muted-foreground text-sm">{t(label)}</p>
                            <p className="mt-1 text-2xl font-semibold tabular-nums"><bdi>{Number(overview.finance[key].total).toLocaleString(locale, { style: 'currency', currency: 'USD' })}</bdi></p>
                            <p className="text-muted-foreground mt-1 text-xs">{overview.finance[key].count === 1 ? t('1 contract') : t(':count contracts', { count: number(overview.finance[key].count) })}</p>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <div className="grid min-w-0 items-start gap-6 lg:grid-cols-2">
                <Card className="overview-card">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <CardTitle><h2>{t('Your work')}</h2></CardTitle>
                            <Link href={client ? '/my-projects' : '/my-proposals'} className="overview-text-link">{t('View all')}</Link>
                        </div>
                        <CardDescription>{client ? t('Recent projects and accepted agreements.') : t('Recent proposals and accepted agreements.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="overview-work-list">
                        {allWork.length ? allWork.map((item) => (
                            <Link key={item.id} href={item.href} className="overview-work-row">
                                <div className="flex min-w-0 flex-wrap items-start justify-between gap-2">
                                    <h3 className="min-w-0 flex-1 font-medium leading-relaxed" dir="auto">{item.title || t('Untitled draft')}</h3>
                                    <Badge variant={item.status === 'awaiting_payment' ? 'secondary' : 'outline'}>{t(STATUS_LABELS[item.status] ?? item.status)}</Badge>
                                </div>
                                <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                    <span>{item.kind === 'contract' ? t('Contract') : item.kind === 'project' ? t('Project') : t('Proposal')}</span>
                                    {item.count !== null && <span>{t(':count proposals', { count: item.count })}</span>}
                                    <time dateTime={item.updated_at} className="ms-auto">{date(item.updated_at)}</time>
                                    <ChevronRight className={ar ? 'size-4 rotate-180' : 'size-4'} aria-hidden="true" />
                                </div>
                            </Link>
                        )) : (
                            <div className="overview-empty"><FileText className="size-7" aria-hidden="true" /><h3 className="font-medium">{t('Your work starts here')}</h3><p>{client ? t('Create a project brief to start receiving proposals.') : t('Find a project and prepare your first proposal.')}</p><Link href={client ? '/my-projects' : '/jobs'} className="overview-text-link">{client ? t('Manage projects') : t('Find projects')}</Link></div>
                        )}
                    </CardContent>
                </Card>
                <Card className="overview-card">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <CardTitle><h2>{t('Recent activity')}</h2></CardTitle>
                            <Link href="/messages" className="overview-text-link">{t('Messages')}</Link>
                        </div>
                        <CardDescription>{t('Messages, proposals and agreements from your workspace.')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {overview.activity.length ? <ol className="overview-activity-list">
                            {overview.activity.map((entry) => {
                                const person = entry.actor ?? auth.user.name;
                                const initials = person.trim().split(/\s+/u).slice(0, 2).map((word) => Array.from(word)[0]).join('');
                                return <li key={entry.id}><Link href={entry.href} className="overview-activity-row">
                                    <Avatar className="size-9 shrink-0" aria-hidden="true"><AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">{initials}</AvatarFallback></Avatar>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm leading-relaxed"><span className="font-medium" dir="auto">{entry.actor ?? t('You')}</span>{' '}<span className="text-muted-foreground">{entry.kind === 'message' ? t('sent a message') : entry.kind === 'proposal' ? t('submitted a proposal') : t('accepted an agreement')}</span></p>
                                        <p className="mt-1 truncate text-sm font-medium" dir="auto">{entry.title || t('Untitled draft')}</p>
                                        {entry.preview && <p className="text-muted-foreground mt-1 line-clamp-2 text-xs leading-relaxed" dir="auto">{entry.preview}</p>}
                                        <time dateTime={entry.created_at} className="text-muted-foreground mt-2 block text-xs">{date(entry.created_at)}</time>
                                    </div>
                                </Link></li>;
                            })}
                        </ol> : <div className="overview-empty"><MessageSquare className="size-7" aria-hidden="true" /><h3 className="font-medium">{t('No workspace activity yet')}</h3><p>{t('Your messages, submitted proposals and accepted agreements will appear here.')}</p></div>}
                    </CardContent>
                </Card>
            </div>

            {(auth.user.is_admin === true || auth.user.is_super_admin === true) && <Card className="overview-card"><CardHeader><CardTitle><h2>{t('Administration')}</h2></CardTitle><CardDescription>{t('Manage your assigned responsibilities.')}</CardDescription></CardHeader><CardContent className="flex flex-wrap gap-4"><Link href="/admin/categories" className="overview-text-link">{t('Manage categories')}</Link>{auth.user.is_super_admin === true && <Link href="/admin/administrators" className="overview-text-link">{t('Manage admin access')}</Link>}</CardContent></Card>}
        </div>
    );
}
export default App1;
