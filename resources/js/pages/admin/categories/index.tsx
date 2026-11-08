// Adapted from local TailAdmin BasicTables/BasicTableOne.tsx and DefaultInputs.tsx.
// Retains the semantic divided table and labelled field; Elancer's Sheet hosts the editor.
// See THIRD_PARTY_NOTICES.md (MIT).
import '@/../css/elancer-admin-categories.css';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Info,
    LockKeyhole,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import CategorySymbol from '@/components/category-symbol';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { copy, type Category } from './copy';

type Props = {
    categories: PaginationData & { data: Category[] };
    status: 'active' | 'deleted';
    notice:
        | 'created'
        | 'updated'
        | 'deleted'
        | 'restored'
        | 'permanentlyDeleted'
        | null;
};

function CategoryEditor({
    category,
    onSaved,
    onProcessingChange,
}: {
    category: Category | null;
    onSaved: () => void;
    onProcessingChange: (processing: boolean) => void;
}) {
    const { auth } = usePage().props;
    const t = copy[auth.user.locale === 'ar' ? 'ar' : 'en'];
    const readOnly = auth.demo;
    const form = useForm({ categoryname: category?.categoryname ?? '' });
    const input = useRef<HTMLInputElement>(null);
    const [requestError, setRequestError] = useState('');

    return (
        <form
            className="category-editor-form"
            onSubmit={(event) => {
                event.preventDefault();
                if (readOnly || form.processing) return;
                setRequestError('');
                onProcessingChange(true);
                const options = {
                    preserveScroll: true,
                    onSuccess: onSaved,
                    onError: () => input.current?.focus(),
                    onFinish: () => onProcessingChange(false),
                    onNetworkError: () => {
                        setRequestError(t.error);
                        return false;
                    },
                    onHttpException: () => {
                        setRequestError(t.error);
                        return false;
                    },
                };
                if (category)
                    form.put(`/admin/categories/${category.id}`, options);
                else form.post('/admin/categories', options);
            }}
        >
            {readOnly && (
                <p className="category-read-only">
                    <LockKeyhole aria-hidden="true" />
                    {t.readOnly}
                </p>
            )}
            {requestError && <Alert variant="error" message={requestError} />}
            <div>
                <Label htmlFor="categoryname">{t.categoryname}</Label>
                <Input
                    ref={input}
                    id="categoryname"
                    name="categoryname"
                    required
                    maxLength={120}
                    dir="auto"
                    disabled={readOnly}
                    readOnly={form.processing}
                    value={form.data.categoryname}
                    onChange={(event) =>
                        form.setData('categoryname', event.target.value)
                    }
                    error={Boolean(form.errors.categoryname)}
                    aria-invalid={Boolean(form.errors.categoryname)}
                    aria-describedby={`categoryname-hint${form.errors.categoryname ? ' categoryname-error' : ''}`}
                />
                <p id="categoryname-hint" className="category-field-hint">
                    {t.hint}
                </p>
                {form.errors.categoryname && (
                    <p
                        id="categoryname-error"
                        role="alert"
                        className="category-field-error"
                    >
                        {form.errors.categoryname}
                    </p>
                )}
            </div>
            {category && (
                <div className="category-stable-link">
                    <p className="font-medium">{t.slug}</p>
                    <bdi dir="ltr">{category.slug}</bdi>
                    <p className="category-field-hint">{t.slugHint}</p>
                </div>
            )}
            <div className="category-editor-actions">
                <Button
                    type="submit"
                    className="category-press"
                    disabled={readOnly || form.processing}
                >
                    {form.processing ? t.saving : category ? t.save : t.create}
                </Button>
                <SheetClose asChild>
                    <Button
                        variant="outline"
                        className="category-press"
                        disabled={form.processing}
                    >
                        {t.cancel}
                    </Button>
                </SheetClose>
            </div>
        </form>
    );
}

export default function Categories({ categories, status, notice }: Props) {
    const { auth } = usePage().props;
    const ar = auth.user.locale === 'ar';
    const t = copy[ar ? 'ar' : 'en'];
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [editorOpen, setEditorOpen] = useState(false);
    const [editorSaving, setEditorSaving] = useState(false);
    const [category, setCategory] = useState<Category | null>(null);
    const [removal, setRemoval] = useState<{
        category: Category;
        forever: boolean;
    } | null>(null);
    const opener = useRef<HTMLElement | null>(null);
    const heading = useRef<HTMLHeadingElement>(null);

    function rememberOpener() {
        opener.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
    }
    function returnFocus(event: Event) {
        event.preventDefault();
        if (opener.current?.isConnected) opener.current.focus();
        else heading.current?.focus();
    }
    function edit(selected: Category | null) {
        if (editorSaving) return;
        rememberOpener();
        setCategory(selected);
        setEditorOpen(true);
    }
    function act(selected: Category, action: 'delete' | 'restore' | 'forever') {
        if (auth.demo || busy) return;
        setBusy(true);
        setError('');
        router.visit(
            `/admin/categories/${selected.id}${action === 'forever' ? '/permanent' : action === 'restore' ? '/restore' : ''}`,
            {
                method: action === 'restore' ? 'post' : 'delete',
                preserveScroll: true,
                onSuccess: () => setRemoval(null),
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

    return (
        <div
            className="workspace-dashboard admin-categories"
            dir={ar ? 'rtl' : 'ltr'}
            lang={ar ? 'ar' : 'en'}
        >
            <Head title={t.title} />
            <div className="category-page-heading">
                <div>
                    <h1 ref={heading} tabIndex={-1}>
                        {t.title}
                    </h1>
                    <p>{t.intro}</p>
                </div>
                <Button
                    className="category-press"
                    onClick={() => edit(null)}
                    startIcon={<Plus className="size-4" aria-hidden="true" />}
                >
                    {t.create}
                </Button>
            </div>
            {notice && (
                <Alert
                    message={t[notice === 'deleted' ? 'deletedNotice' : notice]}
                />
            )}
            {error && <Alert variant="error" message={error} />}
            <div className="category-catalog">
                <nav className="category-status-tabs" aria-label={t.title}>
                    {(['active', 'deleted'] as const).map((value) => (
                        <Link
                            key={value}
                            href={`/admin/categories?status=${value}`}
                            aria-current={status === value ? 'page' : undefined}
                            preserveScroll
                        >
                            {t[value]}
                        </Link>
                    ))}
                </nav>
                <TableScroll label={t.title} className="category-table-scroll">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableCell isHeader>{t.categoryname}</TableCell>
                                <TableCell
                                    isHeader
                                    className="category-actions-cell"
                                >
                                    {t.actions}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <div className="category-name-cell">
                                            <span className="category-symbol">
                                                <CategorySymbol
                                                    name={item.categoryname}
                                                />
                                            </span>
                                            <div className="min-w-0">
                                                <span className="category-name">
                                                    <bdi>
                                                        {item.categoryname}
                                                    </bdi>
                                                </span>
                                                <span className="category-slug">
                                                    <bdi dir="ltr">
                                                        {item.slug}
                                                    </bdi>
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="category-actions-cell">
                                        <div className="category-row-actions">
                                            {status === 'active' && (
                                                <Button
                                                    variant="outline"
                                                    className="category-press"
                                                    onClick={() => edit(item)}
                                                    aria-label={`${t.edit}: ${item.categoryname}`}
                                                    startIcon={
                                                        <Pencil
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    }
                                                >
                                                    <span className="category-action-label">
                                                        {t.edit}
                                                    </span>
                                                </Button>
                                            )}
                                            <Button
                                                variant={
                                                    status === 'active'
                                                        ? 'danger-outline'
                                                        : 'primary'
                                                }
                                                className="category-press"
                                                disabled={auth.demo || busy}
                                                onClick={() => {
                                                    if (status === 'deleted') {
                                                        act(item, 'restore');
                                                        return;
                                                    }
                                                    rememberOpener();
                                                    setRemoval({
                                                        category: item,
                                                        forever: false,
                                                    });
                                                }}
                                                aria-label={`${status === 'active' ? t.remove : t.restore}: ${item.categoryname}`}
                                                startIcon={
                                                    status === 'active' ? (
                                                        <Trash2
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    ) : (
                                                        <RotateCcw
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    )
                                                }
                                            >
                                                <span className="category-action-label">
                                                    {status === 'active'
                                                        ? t.remove
                                                        : t.restore}
                                                </span>
                                            </Button>
                                            {status === 'deleted' && (
                                                <Button
                                                    variant="danger-outline"
                                                    className="category-press"
                                                    disabled={auth.demo || busy}
                                                    onClick={() => {
                                                        rememberOpener();
                                                        setRemoval({
                                                            category: item,
                                                            forever: true,
                                                        });
                                                    }}
                                                    aria-label={`${t.forever}: ${item.categoryname}`}
                                                    startIcon={
                                                        <Trash2
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    }
                                                >
                                                    <span className="category-action-label">
                                                        {t.forever}
                                                    </span>
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
                                        className="category-empty"
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
            <p className="category-lifecycle-hint">
                <Info aria-hidden="true" />
                {t.lifecycleHint}
            </p>
            <Sheet
                open={editorOpen}
                onOpenChange={(open) => {
                    if (!editorSaving) setEditorOpen(open);
                }}
            >
                <SheetContent
                    side={ar ? 'left' : 'right'}
                    className="category-editor"
                    dir={ar ? 'rtl' : 'ltr'}
                    lang={ar ? 'ar' : 'en'}
                    aria-busy={editorSaving}
                    onCloseAutoFocus={returnFocus}
                    onEscapeKeyDown={(event) => {
                        if (editorSaving) event.preventDefault();
                    }}
                    onInteractOutside={(event) => {
                        if (editorSaving) event.preventDefault();
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>{category ? t.edit : t.create}</SheetTitle>
                        <SheetDescription>{t.hint}</SheetDescription>
                    </SheetHeader>
                    <CategoryEditor
                        key={category?.id ?? 'new'}
                        category={category}
                        onSaved={() => setEditorOpen(false)}
                        onProcessingChange={setEditorSaving}
                    />
                </SheetContent>
            </Sheet>
            <Dialog
                open={removal !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) setRemoval(null);
                }}
            >
                <DialogContent
                    className="category-confirmation"
                    dir={ar ? 'rtl' : 'ltr'}
                    onCloseAutoFocus={returnFocus}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {removal?.forever ? t.forever : t.remove}
                        </DialogTitle>
                        <DialogDescription>
                            <bdi className="block font-medium">
                                {removal?.category.categoryname}
                            </bdi>
                            {removal?.forever ? t.confirmForever : t.confirm}
                        </DialogDescription>
                    </DialogHeader>
                    {error && <Alert variant="error" message={error} />}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            className="category-press"
                            disabled={busy}
                            onClick={() => setRemoval(null)}
                        >
                            {t.cancel}
                        </Button>
                        <Button
                            variant="danger"
                            className="category-press"
                            disabled={auth.demo || busy}
                            onClick={() => {
                                if (removal)
                                    act(
                                        removal.category,
                                        removal.forever ? 'forever' : 'delete',
                                    );
                            }}
                        >
                            {removal?.forever ? t.forever : t.remove}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
