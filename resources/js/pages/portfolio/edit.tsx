import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    CaseBody,
    caseImageUrl,
    type CaseContent,
    type CaseImage,
} from '@/components/portfolio-case';
import SkillSelect from '@/components/skill-select';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import {
    ApprovalHistory,
    CaseStatus,
    PortfolioLayout,
    type OwnedCase,
} from './shared';

// Each image is checked and stored on its own; the case lists it once the case is saved.
function CaseImages({
    caseId,
    images,
    errors,
    onChange,
}: {
    caseId: number;
    images: CaseImage[];
    errors: Record<string, string>;
    onChange: (images: CaseImage[]) => void;
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    async function upload(file: File) {
        if (busy) return;
        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
            file.size > 4 * 1024 * 1024
        ) {
            setError(t('Choose a JPG, PNG or WebP image up to 4 MB.'));
            return;
        }
        setBusy(true);
        setError('');
        try {
            const body = new FormData();
            body.append('image', file);
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.slice(11) ?? '',
            );
            const response = await fetch(`/my-portfolio/${caseId}/images`, {
                method: 'POST',
                credentials: 'same-origin',
                body,
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
                signal: AbortSignal.timeout(30000),
            });
            if (response.status === 429) {
                setError(
                    t(
                        'Wait before adding another image. Limit: six per minute and thirty per hour.',
                    ),
                );
                return;
            }
            if ([401, 403, 419].includes(response.status)) {
                setError(t('Sign in again to add an image.'));
                return;
            }
            const data = await response.json();
            if (!response.ok) {
                setError(
                    data.errors?.image?.[0] ??
                        t('We could not save this image. Please try again.'),
                );
                return;
            }
            onChange([...images, { id: data.id, alt: '' }]);
        } catch {
            setError(t('We could not save this image. Please try again.'));
        } finally {
            setBusy(false);
        }
    }
    return (
        <div className="market-stack">
            <h2 className="!mb-0">{t('Images')}</h2>
            <p id="case-images-help" className="market-muted">
                {t(
                    'Add up to six JPG, PNG or WebP images of 4 MB or less. Each image is sent to Sightengine for a workplace-safe content check before it is stored, and it is kept once you save the case study.',
                )}
            </p>
            {images.map((image, i) => (
                <div key={image.id} className="market-stack">
                    <div className="case-image-editor">
                        <img src={caseImageUrl(image.id)} alt="" />
                        <label className="market-field">
                            <span>{t('Image description')}</span>
                            <Input
                                dir="auto"
                                value={image.alt}
                                maxLength={160}
                                error={Boolean(errors[`images.${i}.alt`])}
                                onChange={(event) =>
                                    onChange(
                                        images.map((other, j) =>
                                            j === i
                                                ? {
                                                      ...other,
                                                      alt: event.target.value,
                                                  }
                                                : other,
                                        ),
                                    )
                                }
                            />
                        </label>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                onChange(images.filter((_, j) => j !== i))
                            }
                        >
                            {t('Remove')}
                        </Button>
                    </div>
                    <InputError
                        message={
                            errors[`images.${i}.alt`] ??
                            errors[`images.${i}.id`]
                        }
                    />
                </div>
            ))}
            <InputError message={errors.images} />
            {images.length < 6 && (
                <div className="market-field">
                    <Label
                        htmlFor="case-image"
                        className="flex items-center gap-2"
                    >
                        <ImagePlus
                            className="text-primary size-5"
                            aria-hidden="true"
                        />
                        {t('Add an image')}
                    </Label>
                    <Input
                        id="case-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        disabled={busy}
                        aria-describedby="case-images-help"
                        onChange={(event) => {
                            const file = event.target.files?.[0];
                            event.target.value = '';
                            if (file) void upload(file);
                        }}
                    />
                </div>
            )}
            {error && (
                <p role="alert" className="market-error">
                    {error}
                </p>
            )}
            <span role="status">{busy ? t('Checking image…') : ''}</span>
        </div>
    );
}

export default function Edit({
    case: item,
    contract,
}: {
    case: OwnedCase | null;
    contract: { id: number; title: string } | null;
}) {
    const { t } = useTranslation();
    const linked = item?.contract ?? contract;
    const form = useForm<CaseContent & { contract: number | null }>({
        title: item?.content.title ?? '',
        summary: item?.content.summary ?? '',
        body: item?.content.body ?? '',
        skills: item?.content.skills ?? [],
        links: item?.content.links ?? [],
        images: item?.content.images ?? [],
        contract: contract?.id ?? null,
    });
    const errors = form.errors as Record<string, string>;
    const setLink = (index: number, key: 'label' | 'url', value: string) =>
        form.setData(
            'links',
            form.data.links.map((link, i) =>
                i === index ? { ...link, [key]: value } : link,
            ),
        );
    const title = item ? t('Edit case study') : t('New case study');
    return (
        <PortfolioLayout title={title}>
            <Head title={title} />
            <div className="market-two-column">
                <form
                    className="market-panel market-stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (item)
                            form.put(`/my-portfolio/${item.id}`, {
                                preserveScroll: true,
                            });
                        else form.post('/my-portfolio');
                    }}
                >
                    {item && (
                        <div className="market-actions">
                            <CaseStatus item={item} />
                        </div>
                    )}
                    {item?.moderated && (
                        <p role="status" className="market-muted">
                            {t(
                                'An administrator hid this case study. It is not public and you cannot show it again yourself. You can still edit your private copy.',
                            )}
                        </p>
                    )}
                    <p className="market-muted">
                        {item?.public_content
                            ? t(
                                  'Saving changes only your private copy. The public version stays as it is until the changes are published or approved.',
                              )
                            : linked
                              ? t(
                                    'Saving keeps this case study private. It becomes public only when the client approves the exact text, images and links you send them.',
                                )
                              : t(
                                    'Saving keeps this case study private. Publishing is a separate step on the portfolio page.',
                                )}
                    </p>
                    {linked && (
                        <p>
                            {t('Completed on Elancer:')}{' '}
                            <bdi>{linked.title}</bdi>
                        </p>
                    )}
                    {linked && (
                        <p className="market-muted">
                            {t(
                                'Do not include the name of the client, the price, messages or delivered files unless the client agrees to them being public.',
                            )}
                        </p>
                    )}
                    {(errors.case || errors.contract) && (
                        <p role="alert" className="market-error">
                            {errors.case ?? errors.contract}
                        </p>
                    )}
                    <label className="market-field">
                        <span>{t('Title')}</span>
                        <Input
                            dir="auto"
                            value={form.data.title}
                            maxLength={120}
                            error={Boolean(errors.title)}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                        <InputError message={errors.title} />
                    </label>
                    <label className="market-field">
                        <span>{t('Short summary')}</span>
                        <TextArea
                            rows={2}
                            maxLength={300}
                            dir="auto"
                            value={form.data.summary}
                            error={Boolean(errors.summary)}
                            onChange={(value) => form.setData('summary', value)}
                            hint={t(
                                'One or two sentences shown on your profile. 20 to 300 characters.',
                            )}
                        />
                        <InputError message={errors.summary} />
                    </label>
                    <label className="market-field">
                        <span>{t('What you did')}</span>
                        <TextArea
                            rows={10}
                            maxLength={5000}
                            dir="auto"
                            value={form.data.body}
                            error={Boolean(errors.body)}
                            onChange={(value) => form.setData('body', value)}
                            hint={t(
                                'The problem, your approach and the result. At least 50 characters.',
                            )}
                        />
                        <InputError message={errors.body} />
                    </label>
                    <div className="market-field">
                        <Label htmlFor="case-skills">{t('Skills used')}</Label>
                        <SkillSelect
                            id="case-skills"
                            value={form.data.skills}
                            onChange={(skills) =>
                                form.setData('skills', skills)
                            }
                            error={errors.skills}
                            disabled={form.processing}
                        />
                        <InputError
                            message={
                                errors.skills ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('skills.'),
                                )?.[1]
                            }
                        />
                    </div>
                    {item ? (
                        <CaseImages
                            caseId={item.id}
                            images={form.data.images ?? []}
                            errors={errors}
                            onChange={(images) =>
                                form.setData('images', images)
                            }
                        />
                    ) : (
                        <p className="market-muted">
                            {t(
                                'You can add images after saving the case study for the first time.',
                            )}
                        </p>
                    )}
                    <h2 className="!mb-0">{t('Links')}</h2>
                    <p className="market-muted">
                        {t(
                            'Add up to five HTTPS links, such as a live site, GitHub or Dribbble.',
                        )}
                    </p>
                    {form.data.links.map((link, i) => (
                        <div key={i} className="market-stack">
                            <div className="market-link-editor">
                                <label className="market-field">
                                    <span>{t('Link label')}</span>
                                    <Input
                                        dir="auto"
                                        value={link.label}
                                        maxLength={80}
                                        error={Boolean(
                                            errors[`links.${i}.label`],
                                        )}
                                        onChange={(event) =>
                                            setLink(
                                                i,
                                                'label',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="market-field">
                                    <span>{t('HTTPS address')}</span>
                                    <Input
                                        type="url"
                                        dir="ltr"
                                        value={link.url}
                                        maxLength={2000}
                                        error={Boolean(
                                            errors[`links.${i}.url`],
                                        )}
                                        onChange={(event) =>
                                            setLink(
                                                i,
                                                'url',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData(
                                            'links',
                                            form.data.links.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                >
                                    {t('Remove')}
                                </Button>
                            </div>
                            <InputError
                                message={
                                    errors[`links.${i}.label`] ??
                                    errors[`links.${i}.url`]
                                }
                            />
                        </div>
                    ))}
                    {form.data.links.length < 5 && (
                        <div className="market-actions">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('links', [
                                        ...form.data.links,
                                        { label: '', url: '' },
                                    ])
                                }
                            >
                                {t('Add a link')}
                            </Button>
                        </div>
                    )}
                    <div className="market-actions">
                        <Button type="submit" disabled={form.processing}>
                            {t('Save case study')}
                        </Button>
                        <Link href="/my-portfolio">
                            {t('Back to portfolio')}
                        </Link>
                        <span role="status">
                            {form.recentlySuccessful ? t('Saved') : ''}
                        </span>
                    </div>
                </form>
                <aside className="market-stack">
                    {item?.public_content && (
                        <section className="market-panel market-stack">
                            <h2 className="!mb-0">{t('Public version')}</h2>
                            <h3 className="!mb-0" dir="auto">
                                {item.public_content.title}
                            </h3>
                            <CaseBody content={item.public_content} />
                        </section>
                    )}
                    {item && (
                        <section className="market-panel market-stack">
                            <ApprovalHistory history={item.history} />
                            <Link href="/my-portfolio">
                                {t('Publishing and approval options')}
                            </Link>
                        </section>
                    )}
                </aside>
            </div>
        </PortfolioLayout>
    );
}
