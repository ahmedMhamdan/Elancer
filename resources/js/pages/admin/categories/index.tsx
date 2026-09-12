// Adapted from TailAdmin src/components/tables/BasicTables/BasicTableOne.tsx.
// Keeps bordered container, semantic table/header/body and mapped rows.
// Replaces orders with single-name categories, Elancer tokens and Inertia actions.
// See THIRD_PARTY_NOTICES.md (MIT).
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Pagination, {
    type PaginationData,
} from '@/components/tailadmin/pagination';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
import {
    Table,
    TableScroll,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/tailadmin/table';
import { copy, type Category } from './copy';

type Props = {
    categories: PaginationData & {
        data: Category[];
    };
    status: 'active' | 'deleted';
    notice:
        | 'created'
        | 'updated'
        | 'deleted'
        | 'restored'
        | 'permanentlyDeleted'
        | null;
};
export default function Categories({ categories, status, notice }: Props) {
    const ar = usePage().props.auth.user.locale === 'ar';
    const t = copy[ar ? 'ar' : 'en'];
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    function act(category: Category, forever = false) {
        if (busy) return;
        if (
            forever &&
            !window.confirm(`${category.categoryname}: ${t.confirmForever}`)
        )
            return;
        if (!forever && status === 'active' && !window.confirm(t.confirm))
            return;
        setBusy(true);
        setError('');
        router.visit(
            `/admin/categories/${category.id}${forever ? '/permanent' : status === 'deleted' ? '/restore' : ''}`,
            {
                method: !forever && status === 'deleted' ? 'post' : 'delete',
                preserveScroll: true,
                onError: () => setError(t.error),
                onNetworkError: () => {
                    setError(t.error);
                    return false;
                },
                onHttpException: () => {
                    setError(t.error);
                    return false;
                },
                onFinish: () => setBusy(false),
            },
        );
    }
    const linkClass =
        'inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring';
    return (
        <div
            className="workspace-dashboard space-y-6"
            dir={ar ? 'rtl' : 'ltr'}
            lang={ar ? 'ar' : 'en'}
        >
            <Head title={t.title} />
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold">{t.title}</h1>
                    <p className="text-muted-foreground mt-2">{t.intro}</p>
                </div>
                <Link className={linkClass} href="/admin/categories/create">
                    {t.create}
                </Link>
            </div>
            {notice && (
                <Alert
                    message={t[notice === 'deleted' ? 'deletedNotice' : notice]}
                />
            )}
            {error && <Alert variant="error" message={error} />}
            <nav aria-label={t.title} className="flex gap-2">
                {(['active', 'deleted'] as const).map((value) => (
                    <Link
                        key={value}
                        href={`/admin/categories?status=${value}`}
                        className={`${linkClass} ${status === value ? 'bg-muted' : ''}`}
                        aria-current={status === value ? 'page' : undefined}
                    >
                        {t[value]}
                    </Link>
                ))}
            </nav>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <TableScroll label={t.title}>
                    <Table className="w-full">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell
                                    isHeader
                                    className="text-muted-foreground px-4 py-3 text-start text-sm font-medium"
                                >
                                    {t.title}
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="text-muted-foreground w-px px-4 py-3 text-end text-sm font-medium whitespace-nowrap"
                                >
                                    {t.actions}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-border divide-y">
                            {categories.data.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell className="px-4 py-4 text-start">
                                        <span
                                            dir="auto"
                                            className="block font-medium wrap-anywhere"
                                        >
                                            {category.categoryname}
                                        </span>
                                    </TableCell>
                                    <TableCell className="w-px px-4 py-4 text-end">
                                        <div className="flex items-center justify-end gap-2 whitespace-nowrap">
                                            {status === 'active' && (
                                                <Link
                                                    href={`/admin/categories/${category.id}/edit`}
                                                    className={linkClass}
                                                    aria-label={`${t.edit}: ${category.categoryname}`}
                                                >
                                                    {t.edit}
                                                </Link>
                                            )}
                                            <Button
                                                size="sm"
                                                variant={
                                                    status === 'active'
                                                        ? 'danger-outline'
                                                        : 'primary'
                                                }
                                                disabled={busy}
                                                onClick={() => act(category)}
                                                aria-label={`${status === 'active' ? t.remove : t.restore}: ${category.categoryname}`}
                                            >
                                                {status === 'active'
                                                    ? t.remove
                                                    : t.restore}
                                            </Button>
                                            {status === 'deleted' && (
                                                <Button
                                                    size="sm"
                                                    variant="danger"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        act(category, true)
                                                    }
                                                    aria-label={`${t.forever}: ${category.categoryname}`}
                                                >
                                                    {t.forever}
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!categories.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={2}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {status === 'active'
                                            ? t.empty
                                            : t.emptyDeleted}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableScroll>
            </div>
            <Pagination data={categories} />
        </div>
    );
}
