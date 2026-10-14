import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import HelpLink from '@/components/help-link';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate } from '@/pages/discovery/shared';

type Token = {
    id: number;
    name: string;
    abilities: string[];
    created_at: string | null;
    last_used_at: string | null;
    expires_at: string | null;
    expired: boolean;
};

// Plan 028: a member's own API tokens. The secret arrives as the answer to the
// request that creates a token and lives only in this component's state: it is
// in no page prop, so leaving or reloading the page forgets it.
export default function ApiTokens({
    tokens,
    abilities,
    expiryDays,
    defaultExpiryDays,
    limit,
    allowed,
    endpoint,
}: {
    tokens: Token[];
    abilities: string[];
    expiryDays: number[];
    defaultExpiryDays: number;
    limit: number;
    allowed: boolean;
    endpoint: string;
}) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [chosen, setChosen] = useState<string[]>([]);
    const [days, setDays] = useState(defaultExpiryDays);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [secret, setSecret] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const labels: Record<string, { label: string; hint: string }> = {
        'projects:read': {
            label: t('Read projects'),
            hint: t('The public project list and each project page.'),
        },
        'proposals:read': {
            label: t('Read my proposals'),
            hint: t('Your own drafts and submitted proposals.'),
        },
        'proposals:write': {
            label: t('Write my proposals'),
            hint: t('Save drafts, submit, change and withdraw your proposals.'),
        },
    };
    const routes: { call: string; needs: string | null; what: string }[] = [
        {
            call: 'GET /me',
            needs: null,
            what: t('Your account and what the token may do'),
        },
        {
            call: 'GET /projects',
            needs: 'projects:read',
            what: t('Public projects, with the filters of the Find work page'),
        },
        {
            call: 'GET /projects/{id}',
            needs: 'projects:read',
            what: t('One public project'),
        },
        {
            call: 'GET /me/proposals',
            needs: 'proposals:read',
            what: t('Your proposals'),
        },
        {
            call: 'GET /me/proposals/{id}',
            needs: 'proposals:read',
            what: t('One of your proposals'),
        },
        {
            call: 'PUT /projects/{id}/proposal',
            needs: 'proposals:write',
            what: t('Save a draft or submit a proposal'),
        },
        {
            call: 'POST /me/proposals/{id}/withdraw',
            needs: 'proposals:write',
            what: t('Withdraw a proposal'),
        },
    ];

    const create = async () => {
        if (busy) return;
        setBusy(true);
        setErrors([]);
        try {
            const xsrf =
                document.cookie
                    .split('; ')
                    .find((v) => v.startsWith('XSRF-TOKEN='))
                    ?.slice(11) ?? '';
            const response = await fetch('/settings/tokens', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(xsrf),
                },
                body: JSON.stringify({
                    name,
                    abilities: chosen,
                    expires_in_days: days,
                }),
            });
            // The sign-in is no longer recent: the reload leads through the confirmation screen and back.
            if (response.status === 423) {
                window.location.reload();
                return;
            }
            const result = await response.json();
            if (!response.ok) {
                setErrors(
                    result.errors
                        ? (Object.values(result.errors).flat() as string[])
                        : [
                              result.message ??
                                  t(
                                      'Unable to create the token. Please try again.',
                                  ),
                          ],
                );
                return;
            }
            setSecret(result.token);
            setCopied(false);
            setName('');
            setChosen([]);
            setDays(defaultExpiryDays);
            router.reload({ only: ['tokens'] });
        } catch {
            setErrors([t('Unable to create the token. Please try again.')]);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('API tokens')} />

            <h1 className="sr-only">{t('API tokens')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('API tokens')}
                    description={t(
                        'Let a program you run read projects and manage your proposals for you. A token works like a password for that program: give it only what it needs, and revoke it when you are done.',
                    )}
                />

                {secret && (
                    <div
                        role="status"
                        data-test="token-secret"
                        className="border-primary/40 bg-primary/5 space-y-3 rounded-xl border p-4"
                    >
                        <p className="font-medium">
                            {t(
                                'Copy this token now. It is shown only this once.',
                            )}
                        </p>
                        <code
                            dir="ltr"
                            className="bg-background border-border block rounded-lg border p-3 text-left text-sm break-all select-all"
                        >
                            {secret}
                        </code>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    void navigator.clipboard
                                        .writeText(secret)
                                        .then(() => setCopied(true));
                                }}
                            >
                                {t('Copy token')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setSecret(null)}
                            >
                                {t('I have copied it')}
                            </Button>
                            {copied && (
                                <span className="text-muted-foreground text-sm">
                                    {t('Copied')}
                                </span>
                            )}
                        </div>
                    </div>
                )}

                {!allowed ? (
                    <p className="text-muted-foreground" data-test="tokens-off">
                        {t('This account cannot create API tokens.')}
                    </p>
                ) : (
                    <form
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void create();
                        }}
                    >
                        {!!errors.length && (
                            <Alert variant="error" message={errors.join(' ')} />
                        )}
                        <div className="space-y-2">
                            <label htmlFor="token-name" className="font-medium">
                                {t('Token name')}
                            </label>
                            <Input
                                id="token-name"
                                value={name}
                                maxLength={60}
                                required
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                hint={t(
                                    'A name that tells you where the token is used.',
                                )}
                            />
                        </div>
                        <fieldset className="space-y-4">
                            <legend className="mb-3 font-medium">
                                {t('What it may do')}
                            </legend>
                            {abilities.map((ability) => (
                                <label
                                    key={ability}
                                    className="flex min-h-11 cursor-pointer items-start gap-3"
                                >
                                    <input
                                        type="checkbox"
                                        className="accent-primary mt-1 size-[18px] shrink-0"
                                        checked={chosen.includes(ability)}
                                        onChange={(event) =>
                                            setChosen((current) =>
                                                event.target.checked
                                                    ? [...current, ability]
                                                    : current.filter(
                                                          (item) =>
                                                              item !== ability,
                                                      ),
                                            )
                                        }
                                    />
                                    <span>
                                        <span className="block font-medium">
                                            {labels[ability]?.label ?? ability}
                                        </span>
                                        <span className="text-muted-foreground block text-sm">
                                            {labels[ability]?.hint}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        <fieldset>
                            <legend className="mb-3 font-medium">
                                {t('Expires after')}
                            </legend>
                            <div className="flex flex-wrap gap-x-6 gap-y-2">
                                {expiryDays.map((option) => (
                                    <label
                                        key={option}
                                        className="flex min-h-11 cursor-pointer items-center gap-2"
                                    >
                                        <input
                                            type="radio"
                                            name="expires_in_days"
                                            className="accent-primary size-[18px]"
                                            checked={days === option}
                                            onChange={() => setDays(option)}
                                        />
                                        {t(':count days', { count: option })}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <div className="flex flex-wrap items-center gap-4">
                            <Button
                                type="submit"
                                disabled={
                                    busy ||
                                    !chosen.length ||
                                    tokens.length >= limit
                                }
                            >
                                {t(busy ? 'Creating…' : 'Create token')}
                            </Button>
                            <p className="text-muted-foreground text-sm">
                                {tokens.length >= limit
                                    ? t(
                                          'This account has the most tokens it can hold. Revoke one to create another.',
                                      )
                                    : !chosen.length
                                      ? t(
                                            'Choose at least one thing the token may do.',
                                        )
                                      : ''}
                            </p>
                        </div>
                    </form>
                )}
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Your tokens')}
                    description={t(':count of :limit tokens', {
                        count: tokens.length,
                        limit,
                    })}
                />
                <div className="border-border overflow-hidden rounded-lg border">
                    {!tokens.length && (
                        <div className="p-8 text-center">
                            <p className="font-medium">{t('No tokens yet')}</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {t(
                                    'Create one above when a program needs to work for you.',
                                )}
                            </p>
                        </div>
                    )}
                    {tokens.map((token) => (
                        <div
                            key={token.id}
                            data-test="token-row"
                            className="border-border flex flex-wrap items-start justify-between gap-3 border-b p-4 last:border-b-0"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="font-medium break-words">
                                    <bdi>{token.name}</bdi>
                                    {token.expired && (
                                        <span className="ms-2 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-300">
                                            {t('Expired')}
                                        </span>
                                    )}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {token.abilities
                                        .map(
                                            (ability) =>
                                                labels[ability]?.label ??
                                                ability,
                                        )
                                        .join(' · ')}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {token.created_at && (
                                        <>
                                            {t('Created')}{' '}
                                            <JobDate value={token.created_at} />
                                            {' · '}
                                        </>
                                    )}
                                    {token.last_used_at ? (
                                        <>
                                            {t('Last used')}{' '}
                                            <JobDate
                                                value={token.last_used_at}
                                            />
                                        </>
                                    ) : (
                                        t('Never used')
                                    )}
                                    {token.expires_at && (
                                        <>
                                            {' · '}
                                            {t(
                                                token.expired
                                                    ? 'Expired on'
                                                    : 'Expires on',
                                            )}{' '}
                                            <JobDate value={token.expires_at} />
                                        </>
                                    )}
                                </p>
                            </div>
                            <Button
                                variant="danger-outline"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            t(
                                                'Revoke this token? Any program using it stops working at once.',
                                            ),
                                        )
                                    )
                                        router.delete(
                                            `/settings/tokens/${token.id}`,
                                            { preserveScroll: true },
                                        );
                                }}
                            >
                                {t('Revoke')}
                            </Button>
                        </div>
                    ))}
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title={t('Using a token')}
                    description={t(
                        'Send the token with every request as a bearer token. Answers are JSON, lists come in pages, and each token may make 60 reads and 20 changes a minute.',
                    )}
                />
                <HelpLink article="api-tokens" />
                <pre
                    dir="ltr"
                    className="bg-muted overflow-x-auto rounded-lg p-3 text-left text-sm"
                >
                    <code>{`curl ${endpoint}/projects \\\n  -H "Authorization: Bearer YOUR_TOKEN" \\\n  -H "Accept: application/json"`}</code>
                </pre>
                <ul className="border-border divide-border divide-y rounded-lg border text-sm">
                    {routes.map((route) => (
                        <li key={route.call} className="space-y-1 p-3">
                            <code dir="ltr" className="block text-left">
                                {route.call}
                            </code>
                            <span className="text-muted-foreground block">
                                {route.what}
                                {' · '}
                                {route.needs
                                    ? labels[route.needs].label
                                    : t('Any token')}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

ApiTokens.layout = {
    breadcrumbs: [{ title: 'API tokens', href: '/settings/tokens' }],
};
