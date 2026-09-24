import { Link, useForm } from '@inertiajs/react';
import ComponentCard from './component-card';
import Button from './tailadmin/button';
import Input from './tailadmin/input';
import InputError from './input-error';
import type { MarketplaceProfile } from '@/pages/marketplace-profile';
import { useTranslation } from '@/hooks/use-translation';
import type { WorkLink } from './freelancer-card';
import '../../css/elancer-marketplace.css';
export default function ProfilePublication({
    profile,
}: {
    profile: MarketplaceProfile | null;
}) {
    const { t } = useTranslation();
    const publication = useForm({ published: !profile?.published_at });
    const details = useForm({
        availability: profile?.availability ?? 'available',
        professional_links: (profile?.professional_links ?? []) as WorkLink[],
    });
    const ready = Boolean(
        profile?.headline?.trim() &&
        profile?.bio?.trim() &&
        profile?.skills?.length,
    );
    return (
        <ComponentCard
            title={t('Your public freelancer profile')}
            desc={t(
                'Choose when clients can discover you. Existing proposals stay private to their recipients.',
            )}
        >
            <div className="market-stack">
                <p>
                    {profile?.published_at
                        ? t('Your profile is public.')
                        : t('Your profile is private.')}
                </p>
                {!ready && (
                    <p className="market-muted">
                        {t(
                            'Add a headline, bio and at least one catalog skill before publishing.',
                        )}
                    </p>
                )}
                <div className="market-actions">
                    <Button
                        disabled={
                            publication.processing ||
                            (!profile?.published_at && !ready)
                        }
                        onClick={() => {
                            publication.transform(() => ({
                                published: !profile?.published_at,
                            }));
                            publication.patch('/my-profile/publication', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        {t(
                            profile?.published_at
                                ? 'Make profile private'
                                : 'Publish profile',
                        )}
                    </Button>
                    {profile?.published_at && (
                        <Link href={`/freelancers/${profile.id}`}>
                            {t('View public profile')}
                        </Link>
                    )}
                </div>
                <InputError message={Object.values(publication.errors)[0]} />
                <form
                    className="market-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        details.patch('/my-profile', { preserveScroll: true });
                    }}
                >
                    <label className="market-field">
                        <span>{t('Availability')}</span>
                        <select
                            value={details.data.availability}
                            onChange={(e) =>
                                details.setData('availability', e.target.value)
                            }
                        >
                            <option value="available">
                                {t('Available for work')}
                            </option>
                            <option value="busy">{t('Busy')}</option>
                        </select>
                    </label>
                    <h3>{t('Work and professional links')}</h3>
                    <p className="market-muted">
                        {t(
                            'Share your portfolio, GitHub, Dribbble or a live demo using HTTPS links.',
                        )}
                    </p>
                    {details.data.professional_links.map((link, i) => (
                        <div className="market-link-editor" key={i}>
                            <label className="market-field">
                                <span>{t('Link label')}</span>
                                <Input
                                    value={link.label}
                                    maxLength={80}
                                    onChange={(e) =>
                                        details.setData(
                                            'professional_links',
                                            details.data.professional_links.map(
                                                (v, j) =>
                                                    j === i
                                                        ? {
                                                              ...v,
                                                              label: e.target
                                                                  .value,
                                                          }
                                                        : v,
                                            ),
                                        )
                                    }
                                />
                            </label>
                            <label className="market-field">
                                <span>{t('HTTPS address')}</span>
                                <Input
                                    type="url"
                                    value={link.url}
                                    maxLength={2000}
                                    onChange={(e) =>
                                        details.setData(
                                            'professional_links',
                                            details.data.professional_links.map(
                                                (v, j) =>
                                                    j === i
                                                        ? {
                                                              ...v,
                                                              url: e.target
                                                                  .value,
                                                          }
                                                        : v,
                                            ),
                                        )
                                    }
                                />
                            </label>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    details.setData(
                                        'professional_links',
                                        details.data.professional_links.filter(
                                            (_, j) => j !== i,
                                        ),
                                    )
                                }
                            >
                                {t('Remove')}
                            </Button>
                        </div>
                    ))}
                    {Object.values(details.errors).map((message, i) => (
                        <InputError key={i} message={message} />
                    ))}
                    <div className="market-actions">
                        {details.data.professional_links.length < 5 && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    details.setData('professional_links', [
                                        ...details.data.professional_links,
                                        { label: '', url: '' },
                                    ])
                                }
                            >
                                {t('Add link')}
                            </Button>
                        )}
                        <Button type="submit" disabled={details.processing}>
                            {t('Save availability and links')}
                        </Button>
                        {details.recentlySuccessful && (
                            <span role="status">{t('Saved')}</span>
                        )}
                    </div>
                </form>
            </div>
        </ComponentCard>
    );
}
