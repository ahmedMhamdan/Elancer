import { Link, useForm } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';

export default function StartConversation({
    proposalId,
    conversationId,
    canStart,
}: {
    proposalId: number;
    conversationId: number | null;
    canStart: boolean;
}) {
    const { t } = useTranslation();
    const form = useForm({ body: '', client_token: crypto.randomUUID() });
    if (conversationId)
        return (
            <Link
                className="job-primary-link"
                href={`/messages/${conversationId}`}
            >
                {t('Open conversation')}
            </Link>
        );
    if (!canStart) return null;
    return (
        <section className="market-panel">
            <h2>{t('Start a hiring conversation')}</h2>
            <form
                className="market-stack"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/proposals/${proposalId}/conversation`);
                }}
            >
                <label className="market-field">
                    <span>{t('First message')}</span>
                    <TextArea
                        value={form.data.body}
                        onChange={(value) => form.setData('body', value)}
                        required
                        maxLength={10000}
                    />
                </label>
                <InputError message={form.errors.body} />
                <div>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.data.body.trim()}
                    >
                        {t('Start conversation')}
                    </Button>
                </div>
            </form>
        </section>
    );
}
