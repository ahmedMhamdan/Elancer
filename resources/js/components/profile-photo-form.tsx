// Reuses the existing TailAdmin-backed crop editor and form controls.
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ComponentCard from '@/components/component-card';
import PhotoEditor from '@/components/photo-editor';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import { useTranslation } from '@/hooks/use-translation';

export default function ProfilePhotoForm() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [source, setSource] = useState<File | null>(null);
    const [photo, setPhoto] = useState<File | null>(null);
    const [preview, setPreview] = useState<string>();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [saved, setSaved] = useState(false);
    useEffect(() => {
        if (!photo) {
            setPreview(undefined);
            return;
        }
        const url = URL.createObjectURL(photo);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [photo]);
    async function upload() {
        if (!photo || busy) return;
        setBusy(true);
        setError('');
        setSaved(false);
        try {
            const body = new FormData();
            body.append('photo', photo);
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.slice(11) ?? '',
            );
            const response = await fetch('/my-profile/photo', {
                method: 'POST',
                credentials: 'same-origin',
                body,
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
                signal: AbortSignal.timeout(20000),
            });
            if (response.status === 429) {
                setError(
                    t(
                        'Wait before uploading another photo. Limit: one per minute and three per hour.',
                    ),
                );
                return;
            }
            if ([401, 403, 419].includes(response.status)) {
                setError(t('Sign in again to change your photo.'));
                return;
            }
            const data = await response.json();
            if (!response.ok) {
                setError(
                    data.errors?.photo?.[0] ??
                        t('We could not save your photo. Please try again.'),
                );
                return;
            }
            router.replaceProp('auth.user.avatar', data.avatar);
            setPhoto(null);
            setSaved(true);
        } catch {
            setError(
                t(
                    'Photo upload could not be confirmed. Reload to check your current photo.',
                ),
            );
        } finally {
            setBusy(false);
        }
    }
    return (
        <ComponentCard
            title={t('Profile photo')}
            desc={t('Use a clear, workplace-safe photo.')}
        >
            {source && (
                <PhotoEditor
                    file={source}
                    onClose={() => setSource(null)}
                    onApply={(file) => {
                        setPhoto(file);
                        setSource(null);
                        setError('');
                        setSaved(false);
                    }}
                />
            )}
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    void upload();
                }}
                className="space-y-4"
            >
                <div className="flex flex-wrap items-center gap-4">
                    {(preview || auth.user.avatar) && (
                        <img
                            src={preview || auth.user.avatar || undefined}
                            alt={t('Your selected profile photo')}
                            className="size-20 rounded-full object-cover"
                        />
                    )}
                    <div className="min-w-0 flex-1">
                        <Label htmlFor="profile-photo">
                            {t('Change photo')}
                        </Label>
                        <Input
                            id="profile-photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            disabled={busy}
                            aria-describedby="profile-photo-help profile-photo-error"
                            aria-invalid={!!error}
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                event.target.value = '';
                                setSaved(false);
                                if (!file) return;
                                if (
                                    ![
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                    ].includes(file.type) ||
                                    file.size > 2 * 1024 * 1024
                                ) {
                                    setError(
                                        t(
                                            'Choose a JPG, PNG or WebP photo up to 2 MB.',
                                        ),
                                    );
                                    return;
                                }
                                setError('');
                                setSource(file);
                            }}
                        />
                    </div>
                </div>
                <div
                    id="profile-photo-help"
                    className="text-muted-foreground space-y-1 text-sm"
                >
                    <p>{t('Choose a JPG, PNG or WebP photo up to 2 MB.')}</p>
                    <p>
                        {t(
                            'Your cropped photo is sent to Sightengine for a workplace-safe content check before saving.',
                        )}
                    </p>
                    <p>
                        {t(
                            'Upload attempts are limited to one per minute and three per hour.',
                        )}
                    </p>
                </div>
                <p
                    id="profile-photo-error"
                    role="alert"
                    className="text-destructive text-sm"
                >
                    {error}
                </p>
                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={!photo || busy}>
                        {busy ? t('Checking photo…') : t('Save photo')}
                    </Button>
                    {photo && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setPhoto(null)}
                        >
                            {t('Cancel')}
                        </Button>
                    )}
                    <span role="status" className="text-primary text-sm">
                        {saved && t('Photo updated')}
                    </span>
                </div>
            </form>
        </ComponentCard>
    );
}
