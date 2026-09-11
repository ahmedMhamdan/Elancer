// Adapted from TailAdmin src/components/form/form-elements/DefaultInputs.tsx.
// Retains ComponentCard > space-y-6 > Label/Input composition; adds Inertia
// validation, a single name in either language and accessible error associations. MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ComponentCard from '@/components/component-card';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import { copy, type Category } from './copy';

export default function CategoryForm({
    category,
}: {
    category: Category | null;
}) {
    const ar = usePage().props.auth.user.locale === 'ar';
    const t = copy[ar ? 'ar' : 'en'];
    const form = useForm({
        categoryname: category?.categoryname ?? '',
    });
    useEffect(() => {
        if (Object.keys(form.errors).length) {
            document
                .querySelector<HTMLInputElement>('[aria-invalid="true"]')
                ?.focus();
        }
    }, [form.errors]);
    const [requestError, setRequestError] = useState('');
    const title = category ? t.save : t.create;
    return (
        <div
            className="workspace-dashboard space-y-6"
            dir={ar ? 'rtl' : 'ltr'}
            lang={ar ? 'ar' : 'en'}
        >
            <Head title={title} />
            <Link
                href="/admin/categories"
                className="text-primary inline-flex min-h-11 items-center underline"
            >
                {t.back}
            </Link>
            <h1 className="text-3xl font-semibold">
                {category ? t.edit : t.create}
            </h1>
            <div className="max-w-2xl">
                <ComponentCard title={title}>
                    <form
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            setRequestError('');
                            const options = {
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
                                form.put(
                                    `/admin/categories/${category.id}`,
                                    options,
                                );
                            else form.post('/admin/categories', options);
                        }}
                    >
                        {requestError && (
                            <p role="alert" className="text-[var(--el-error)]">
                                {requestError}
                            </p>
                        )}
                        {form.hasErrors && (
                            <p role="alert" className="text-[var(--el-error)]">
                                {t.invalid}
                            </p>
                        )}
                        <div>
                            <Label htmlFor="categoryname">
                                {t.categoryname}
                            </Label>
                            <Input
                                id="categoryname"
                                name="categoryname"
                                required
                                maxLength={120}
                                dir="auto"
                                value={form.data.categoryname}
                                onChange={(event) =>
                                    form.setData(
                                        'categoryname',
                                        event.target.value,
                                    )
                                }
                                error={Boolean(form.errors.categoryname)}
                                style={
                                    form.errors.categoryname
                                        ? { borderColor: 'var(--el-error)' }
                                        : undefined
                                }
                                aria-invalid={Boolean(form.errors.categoryname)}
                                aria-describedby="categoryname-hint categoryname-error"
                            />
                            <p
                                id="categoryname-hint"
                                className="text-muted-foreground mt-2 text-sm"
                            >
                                {t.hint}
                            </p>
                            <p
                                id="categoryname-error"
                                className="mt-2 text-sm text-[var(--el-error)]"
                            >
                                {form.errors.categoryname}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? t.saving : title}
                            </Button>
                            <Link
                                className="text-primary inline-flex min-h-11 items-center underline"
                                href="/admin/categories"
                            >
                                {t.cancel}
                            </Link>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </div>
    );
}
