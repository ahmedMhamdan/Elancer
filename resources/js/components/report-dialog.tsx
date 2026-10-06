// Uses the adapted TailAdmin Modal, Radio, TextArea and Button (MIT: THIRD_PARTY_NOTICES.md).
import { useForm } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
import Modal from '@/components/tailadmin/modal';
import Radio from '@/components/tailadmin/radio';
import TextArea from '@/components/tailadmin/textarea';
import { useReportLabels, type ReportTarget } from '@/hooks/use-report-labels';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/** The one Report action, reused on projects, profiles, case studies, messages and contracts. */
export default function ReportDialog({
    type,
    id,
    label,
    className,
}: {
    type: ReportTarget;
    id: number;
    label: string;
    className?: string;
}) {
    const { t } = useTranslation();
    const { reasons } = useReportLabels();
    const field = useId();
    const [open, setOpen] = useState(false);
    const form = useForm({
        target_type: type as string,
        target_id: id,
        reason: '',
        explanation: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const length = form.data.explanation.trim().length;
    const [failure, setFailure] = useState('');
    const close = () => {
        setOpen(false);
        setFailure('');
        form.reset();
        form.clearErrors();
    };
    return (
        <>
            <button
                type="button"
                className={cn(
                    'focus-visible:outline-ring inline-flex min-h-11 cursor-pointer items-center gap-1.5 text-sm underline underline-offset-4 opacity-80 hover:opacity-100 focus-visible:outline-2',
                    className,
                )}
                onClick={() => setOpen(true)}
            >
                <Flag size={15} aria-hidden="true" />
                {label}
            </button>
            <Modal
                open={open}
                onClose={close}
                title={t('Send a report')}
                description={t(
                    'The Elancer team reviews every report. The member you report is not told who sent it.',
                )}
            >
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setFailure('');
                        form.post('/reports', {
                            preserveScroll: true,
                            onSuccess: close,
                            // Too many reports in a short time, or the page is no longer available.
                            onHttpException: () => {
                                setFailure(
                                    t(
                                        'The report could not be sent. If you sent several reports just now, wait a minute and try again.',
                                    ),
                                );
                                return false;
                            },
                        });
                    }}
                >
                    <fieldset className="space-y-3">
                        <legend className="mb-2 font-medium">
                            {t('Reason')}
                        </legend>
                        {Object.entries(reasons).map(([value, text]) => (
                            <Radio
                                key={value}
                                id={field + value}
                                name={field + 'reason'}
                                value={value}
                                label={text}
                                checked={form.data.reason === value}
                                onChange={(choice) =>
                                    form.setData('reason', choice)
                                }
                            />
                        ))}
                        <InputError message={errors.reason} />
                    </fieldset>
                    <div>
                        <Label htmlFor={field + 'text'}>
                            {t('What happened?')}
                        </Label>
                        <TextArea
                            id={field + 'text'}
                            rows={5}
                            required
                            minLength={10}
                            maxLength={2000}
                            dir="auto"
                            placeholder=""
                            value={form.data.explanation}
                            onChange={(value) =>
                                form.setData('explanation', value)
                            }
                            aria-describedby={field + 'help'}
                            aria-invalid={!!errors.explanation}
                        />
                        <p
                            id={field + 'help'}
                            className="text-muted-foreground mt-2 text-sm"
                        >
                            {t(
                                'Write 10 to 2,000 characters. Leave out passwords and payment details.',
                            )}
                        </p>
                        <InputError message={errors.explanation} />
                    </div>
                    {type === 'contract' && (
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'A report does not complete, cancel or refund a contract. Those stay between you and the other member.',
                            )}
                        </p>
                    )}
                    {(errors.report || failure) && (
                        <p role="alert" className="text-destructive text-sm">
                            {errors.report || failure}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.reason ||
                                length < 10
                            }
                        >
                            {form.processing
                                ? t('Sending...')
                                : t('Send report')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={close}
                        >
                            {t('Cancel')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
