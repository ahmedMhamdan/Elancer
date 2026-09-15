import { useTranslation } from '@/hooks/use-translation';
// TailAdmin ComponentCard and DefaultInputs adaptations; private identity uploads.
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import ComponentCard from '@/components/component-card';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import IdentityUpload from '@/components/identity-upload';

export type IdentityStatus = {
    status: 'pending' | 'approved' | 'rejected';
    reason: string | null;
    reviewed_at: string | null;
} | null;
export default function IdentityVerification({
    identity,
}: {
    identity: IdentityStatus;
}) {
    const { t } = useTranslation();

    const element = useRef<HTMLFormElement>(null);
    const form = useForm<{
        government_id: File | null;
        selfie: File | null;
        consent: boolean;
    }>({ government_id: null, selfie: null, consent: false });
    const title =
        identity?.status === 'approved'
            ? 'Identity approved'
            : identity?.status === 'pending'
              ? 'Identity review pending'
              : identity?.status === 'rejected'
                ? 'Identity needs resubmission'
                : 'Verify your identity';
    return (
        <ComponentCard
            title={t(title)}
            desc={t('Identity verification for clients and freelancers.')}
        >
            {identity?.status === 'pending' && (
                <p className="text-muted-foreground text-sm">
                    {t(
                        'Your ID photo and selfie have been submitted. A super admin will review them. You are not yet verified.',
                    )}
                </p>
            )}
            {identity?.status === 'approved' && (
                <p className="text-primary text-sm">
                    {t(
                        'A super admin approved your identity submission. The uploaded images have been removed.',
                    )}
                </p>
            )}
            {identity?.status === 'rejected' && (
                <p role="status" className="text-muted-foreground text-sm">
                    {t('Review feedback:')}
                    {identity.reason}
                </p>
            )}
            {(!identity || identity.status === 'rejected') && (
                <form
                    ref={element}
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/my-profile/identity', {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                element.current?.reset();
                            },
                        });
                    }}
                    className="space-y-5"
                >
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Upload a clear photo of your government-issued ID and a separate selfie showing your face. JPEG or PNG, up to 2 MB each. Images are encrypted, private, and accessible only to super admins for review. They are removed after review or account deletion. This is a manual review, not an automated authenticity check.',
                        )}
                    </p>
                    <IdentityUpload
                        id="government-id"
                        label={t('Government ID photo')}
                        file={form.data.government_id}
                        onChange={(file) => form.setData('government_id', file)}
                        error={form.errors.government_id}
                        disabled={form.processing}
                    />
                    <IdentityUpload
                        id="identity-selfie"
                        label={t('Selfie')}
                        file={form.data.selfie}
                        onChange={(file) => form.setData('selfie', file)}
                        error={form.errors.selfie}
                        disabled={form.processing}
                    />
                    <label className="flex items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            required
                            checked={form.data.consent}
                            onChange={(e) =>
                                form.setData('consent', e.target.checked)
                            }
                            className="mt-1 size-4 shrink-0"
                        />
                        {t(
                            'I agree to submit these images for private manual identity review.',
                        )}
                    </label>
                    <InputError message={form.errors.consent} />
                    {form.progress && (
                        <p role="status">
                            {t('Uploading:')}
                            {form.progress.percentage}%
                        </p>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        {form.processing
                            ? t('Submitting…')
                            : t('Submit for verification')}
                    </Button>
                </form>
            )}
        </ComponentCard>
    );
}
