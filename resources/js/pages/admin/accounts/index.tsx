// Adapted from TailAdmin src/components/tables/BasicTables/BasicTableOne.tsx through the
// existing table and pagination adaptations, with the administrators page's search form.
// Reuses TailAdmin Input, Label and Button. MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, useForm } from '@inertiajs/react';
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
import { useTranslation } from '@/hooks/use-translation';

type Row = {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'suspended' | 'deactivated';
    email_verified_at: string | null;
    is_admin: boolean;
    is_super_admin: boolean;
};

export default function Accounts({
    users,
    search,
    status,
}: {
    users: PaginationData & { data: Row[] };
    search: string;
    status: 'all' | 'suspended';
}) {
    const { t } = useTranslation();
    const form = useForm({ q: search, status });
    const head =
        'text-muted-foreground px-4 py-3 text-start text-sm font-medium';
    const linkClass =
        'inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring';
    const tabs = {
        all: t('All accounts'),
        suspended: t('Suspended accounts'),
    };
    const statuses = {
        active: t('Active'),
        suspended: t('Suspended'),
        deactivated: t('Deactivated'),
    };
    const query = (value: string) =>
        '/admin/accounts?' +
        new URLSearchParams({
            ...(search ? { q: search } : {}),
            status: value,
        }).toString();
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Accounts')} />
            <div className="workspace-page-heading">
                <h1>{t('Accounts')}</h1>
                <p>
                    {t(
                        'Find a member to see their account status, suspend them or reinstate them.',
                    )}
                </p>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/admin/accounts');
                }}
                className="flex flex-wrap items-end gap-3"
            >
                <div className="w-full max-w-md">
                    <Label htmlFor="account-search">
                        {t('Name or email address')}
                    </Label>
                    <Input
                        id="account-search"
                        value={form.data.q}
                        onChange={(event) =>
                            form.setData('q', event.target.value)
                        }
                        maxLength={120}
                        dir="auto"
                    />
                </div>
                <Button type="submit" disabled={form.processing}>
                    {t('Find account')}
                </Button>
            </form>
            <nav aria-label={t('Accounts')} className="flex gap-2">
                {(['all', 'suspended'] as const).map((value) => (
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
                <TableScroll label={t('Accounts')}>
                    <Table className="w-full">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell isHeader className={head}>
                                    {t('Account')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Role')}
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
                            {users.data.map((account) => (
                                <TableRow key={account.id}>
                                    <TableCell className="px-4 py-4 text-start">
                                        <div className="min-w-48">
                                            <span className="block font-medium wrap-anywhere">
                                                <bdi>{account.name}</bdi>
                                            </span>
                                            <span className="text-muted-foreground mt-1 block text-sm wrap-anywhere">
                                                <bdi dir="ltr">
                                                    {account.email}
                                                </bdi>
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 whitespace-nowrap">
                                        {account.is_super_admin
                                            ? t('Super administrator')
                                            : account.is_admin
                                              ? t('Administrator')
                                              : t('Member')}
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <p className="font-medium whitespace-nowrap">
                                            {statuses[account.status]}
                                        </p>
                                        {!account.email_verified_at && (
                                            <p className="text-muted-foreground text-sm whitespace-nowrap">
                                                {t('Email not verified')}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="w-px px-4 py-4 text-end">
                                        <Link
                                            href={`/admin/accounts/${account.id}`}
                                            className={`${linkClass} whitespace-nowrap`}
                                            aria-label={`${t('Open account')}: ${account.name}`}
                                        >
                                            {t('Open account')}
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!users.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {t('No accounts match.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableScroll>
            </div>
            <Pagination data={users} />
        </div>
    );
}
