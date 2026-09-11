// Adapted from the inspected TailAdmin DefaultInputs.tsx via the Elancer category form.
// Preserves ComponentCard/Label/input composition; uses TailAdmin TextArea for audit reason.
// MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ComponentCard from '@/components/component-card';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
import TextArea from '@/components/tailadmin/textarea';
import { copy, type Account } from './copy';

export default function AdministratorEdit({ account }: { account: Account }) {
    const ar = usePage().props.auth.user.locale === 'ar';
    const t = copy[ar ? 'ar' : 'en'];
    const form = useForm({ is_admin: !account.is_admin, reason: '' });
    const [failure, setFailure] = useState('');
    useEffect(() => {
        if (form.errors.reason)
            document.getElementById('access-reason')?.focus();
    }, [form.errors.reason]);
    const title = form.data.is_admin ? t.grant : t.revoke;
    return (
        <div
            className="workspace-dashboard space-y-6"
            dir={ar ? 'rtl' : 'ltr'}
            lang={ar ? 'ar' : 'en'}
        >
            <Head title={title} />
            <Link
                href="/admin/administrators"
                className="text-primary inline-flex min-h-11 items-center underline"
            >
                {t.back}
            </Link>
            <h1 className="text-3xl font-semibold">{title}</h1>
            <div className="max-w-2xl">
                <ComponentCard title={account.name}>
                    <p
                        dir="ltr"
                        className="text-muted-foreground text-sm wrap-anywhere"
                    >
                        {account.email}
                    </p>
                    <p>{form.data.is_admin ? t.grantHelp : t.revokeHelp}</p>
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            setFailure('');
                            form.put(`/admin/administrators/${account.id}`, {
                                onNetworkError: () => {
                                    setFailure(t.failed);
                                    return false;
                                },
                                onHttpException: () => {
                                    setFailure(t.failed);
                                    return false;
                                },
                            });
                        }}
                    >
                        {(failure || form.errors.is_admin) && (
                            <p role="alert" className="text-[var(--el-error)]">
                                {failure || form.errors.is_admin}
                            </p>
                        )}
                        <div>
                            <Label htmlFor="access-reason">{t.reason}</Label>
                            <TextArea
                                id="access-reason"
                                name="reason"
                                placeholder=""
                                required
                                maxLength={500}
                                dir="auto"
                                value={form.data.reason}
                                onChange={(value) =>
                                    form.setData('reason', value)
                                }
                                aria-invalid={Boolean(form.errors.reason)}
                                aria-describedby="reason-help reason-error"
                            />
                            <p
                                id="reason-help"
                                className="text-muted-foreground mt-2 text-sm"
                            >
                                {t.reasonHelp}
                            </p>
                            <p
                                id="reason-error"
                                role="alert"
                                className="mt-2 text-sm text-[var(--el-error)]"
                            >
                                {form.errors.reason}
                            </p>
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? t.saving : title}
                        </Button>
                    </form>
                </ComponentCard>
            </div>
        </div>
    );
}
