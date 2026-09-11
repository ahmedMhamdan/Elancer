// Adapted from the inspected TailAdmin BasicTableOne.tsx and Elancer category table.
// Retains semantic wrappers/mapped rows; adds account search and protected role links.
// Reuses TailAdmin Input, Label and Button. MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableCell,
} from '@/components/tailadmin/table';
import { copy, type Account } from './copy';

type Props = {
    users: {
        data: Account[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    search: string;
    notice: boolean | null;
};
export default function Administrators({ users, search, notice }: Props) {
    const ar = usePage().props.auth.user.locale === 'ar';
    const t = copy[ar ? 'ar' : 'en'];
    const form = useForm({ q: search });
    const link =
        'inline-flex min-h-11 items-center text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring';
    return (
        <div
            className="workspace-dashboard space-y-6"
            dir={ar ? 'rtl' : 'ltr'}
            lang={ar ? 'ar' : 'en'}
        >
            <Head title={t.title} />
            <div>
                <h1 className="text-3xl font-semibold">{t.title}</h1>
                <p className="text-muted-foreground mt-2">{t.intro}</p>
            </div>
            {notice && (
                <p
                    role="status"
                    className="border-border bg-card rounded-lg border p-4"
                >
                    {t.updated}
                </p>
            )}
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/admin/administrators');
                }}
                className="flex flex-wrap items-end gap-3"
            >
                <div className="w-full max-w-md">
                    <Label htmlFor="account-search">{t.search}</Label>
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
                    {t.find}
                </Button>
            </form>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <div className="max-w-full overflow-x-auto">
                    <Table className="w-full table-fixed">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell
                                    isHeader
                                    className="text-muted-foreground px-4 py-3 text-start text-sm font-medium"
                                >
                                    {t.account}
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="text-muted-foreground w-2/5 px-4 py-3 text-start text-sm font-medium"
                                >
                                    {t.access}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-border divide-y">
                            {users.data.map((account) => (
                                <TableRow key={account.id}>
                                    <TableCell className="px-4 py-4 text-start">
                                        <span
                                            dir="auto"
                                            className="block font-medium wrap-anywhere"
                                        >
                                            {account.name}
                                        </span>
                                        <span
                                            dir="ltr"
                                            className="text-muted-foreground mt-1 block text-sm wrap-anywhere"
                                        >
                                            {account.email}
                                        </span>
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            {t[account.status]} ·{' '}
                                            {account.email_verified_at
                                                ? t.verified
                                                : t.unverified}
                                        </p>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 text-start">
                                        <p>
                                            {account.is_super_admin
                                                ? t.superAdmin
                                                : account.is_admin
                                                  ? t.admin
                                                  : t.member}
                                        </p>
                                        {account.is_super_admin ? (
                                            <p className="text-muted-foreground mt-2 text-sm">
                                                {t.protected}
                                            </p>
                                        ) : (
                                            <Link
                                                className={link}
                                                href={`/admin/administrators/${account.id}/edit`}
                                                aria-label={`${t.manage}: ${account.name}`}
                                            >
                                                {t.manage}
                                            </Link>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!users.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={2}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        {t.empty}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
            <nav
                aria-label={ar ? 'ترقيم الصفحات' : 'Pagination'}
                className="flex flex-wrap items-center justify-between gap-4"
            >
                <p className="text-muted-foreground text-sm">
                    {users.current_page} / {users.last_page} · {users.total}
                </p>
                <div className="flex gap-4">
                    {users.prev_page_url && (
                        <Link className={link} href={users.prev_page_url}>
                            {t.previous}
                        </Link>
                    )}
                    {users.next_page_url && (
                        <Link className={link} href={users.next_page_url}>
                            {t.next}
                        </Link>
                    )}
                </div>
            </nav>
        </div>
    );
}
