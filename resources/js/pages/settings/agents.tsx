import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import HelpLink from '@/components/help-link';
import Button from '@/components/tailadmin/button';
import { useTokenAbilities } from '@/hooks/use-token-abilities';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate } from '@/pages/discovery/shared';

// Plan 033: an app the member approved through its Connect button.
type Connection = {
    id: string;
    name: string | null;
    returnsTo: string;
    connected_at: string | null;
    renewed_at: string | null;
    ends_at: string | null;
};

// Plan 033, its own Settings tab since plan 032 part 9: how an AI agent reaches Elancer
// through MCP, and the apps connected through Connect. Tokens stay on API tokens.
export default function AiAgents({
    mcpEndpoint,
    connections,
    connectionLimit,
}: {
    mcpEndpoint: string;
    connections: Connection[];
    connectionLimit: number;
}) {
    const { t } = useTranslation();
    const labels = useTokenAbilities();
    const [addressCopied, setAddressCopied] = useState(false);

    // The tools an AI agent finds at the MCP address, each with the ability it needs.
    const tools: { name: string; needs: string | null; what: string }[] = [
        {
            name: 'get_account',
            needs: null,
            what: t('Your account and what the connection may do'),
        },
        {
            name: 'search_projects',
            needs: 'projects:read',
            what: t('Search public projects'),
        },
        {
            name: 'get_project',
            needs: 'projects:read',
            what: t('One public project'),
        },
        {
            name: 'list_my_proposals',
            needs: 'proposals:read',
            what: t('Your proposals'),
        },
        {
            name: 'get_my_proposal',
            needs: 'proposals:read',
            what: t('One of your proposals'),
        },
        {
            name: 'save_proposal',
            needs: 'proposals:write',
            what: t('Save a draft or submit a proposal'),
        },
        {
            name: 'withdraw_proposal',
            needs: 'proposals:write',
            what: t('Withdraw a proposal'),
        },
        {
            name: 'list_my_projects',
            needs: 'hiring:read',
            what: t('Your projects as a client, with their counts'),
        },
        {
            name: 'list_received_proposals',
            needs: 'hiring:read',
            what: t('The proposals one of your projects received'),
        },
        {
            name: 'get_received_proposal',
            needs: 'hiring:read',
            what: t('One received proposal, as you read it'),
        },
    ];

    return (
        <>
            <Head title={t('AI agents')} />

            <h1 className="sr-only">{t('AI agents')}</h1>

            <div className="space-y-4" data-test="connect-agent">
                <Heading
                    variant="small"
                    title={t('Connect an AI agent')}
                    description={t(
                        'AI agents and apps that support MCP can work with your account through one address. An app with a Connect button signs you in here and asks for your approval; any other agent uses a token from API tokens.',
                    )}
                />
                <HelpLink article="ai-agents" />
                <div className="flex flex-wrap items-center gap-3">
                    <code
                        dir="ltr"
                        className="bg-muted min-w-0 flex-1 rounded-lg p-3 text-left text-sm break-all select-all"
                    >
                        {mcpEndpoint}
                    </code>
                    <Button
                        variant="outline"
                        onClick={() => {
                            void navigator.clipboard
                                .writeText(mcpEndpoint)
                                .then(() => setAddressCopied(true));
                        }}
                    >
                        {t('Copy address')}
                    </Button>
                    {addressCopied && (
                        <span className="text-muted-foreground text-sm">
                            {t('Copied')}
                        </span>
                    )}
                </div>
                <ul className="list-disc space-y-2 ps-5 text-sm">
                    <li>
                        {t(
                            'With a Connect button (for example Claude, ChatGPT, Cursor or VS Code): add Elancer as a custom connector with this address, then approve it on the page Elancer opens.',
                        )}
                    </li>
                    <li>
                        {t(
                            'With a header instead (for example Claude Code): create a token on API tokens with what the agent may do, then add the server like this:',
                        )}
                    </li>
                </ul>
                <pre
                    dir="ltr"
                    className="bg-muted overflow-x-auto rounded-lg p-3 text-left text-sm"
                >
                    <code>{`claude mcp add --transport http elancer ${mcpEndpoint} \\\n  --header "Authorization: Bearer YOUR_TOKEN"`}</code>
                </pre>
                <Link
                    href="/settings/tokens"
                    data-test="agents-tokens-link"
                    className="text-primary inline-flex min-h-6 items-center text-sm font-medium underline underline-offset-4"
                >
                    {t('Open API tokens')}
                </Link>
                <p className="text-muted-foreground text-sm">
                    {t(
                        'Elancer’s free hosting sleeps when nobody uses it, so the first call after a quiet time can take up to a minute.',
                    )}
                </p>
                <ul className="border-border divide-border divide-y rounded-lg border text-sm">
                    {tools.map((tool) => (
                        <li key={tool.name} className="space-y-1 p-3">
                            <code dir="ltr" className="block text-left">
                                {tool.name}
                            </code>
                            <span className="text-muted-foreground block">
                                {tool.what}
                                {' · '}
                                {tool.needs
                                    ? labels[tool.needs].label
                                    : t('Any token')}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Connected apps')}
                    description={t(':count of :limit apps', {
                        count: connections.length,
                        limit: connectionLimit,
                    })}
                />
                <div className="border-border overflow-hidden rounded-lg border">
                    {!connections.length && (
                        <div className="p-8 text-center">
                            <p className="font-medium">
                                {t('No connected apps')}
                            </p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {t(
                                    'An app you approve through its Connect button appears here. It may use every tool above.',
                                )}
                            </p>
                        </div>
                    )}
                    {connections.map((app) => (
                        <div
                            key={app.id}
                            data-test="connection-row"
                            className="border-border flex flex-wrap items-start justify-between gap-3 border-b p-4 last:border-b-0"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="font-medium break-words">
                                    <bdi>{app.name ?? t('An app')}</bdi>
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t('Returns to')}{' '}
                                    <code dir="ltr" className="break-all">
                                        {app.returnsTo}
                                    </code>
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {app.connected_at && (
                                        <>
                                            {t('Connected')}{' '}
                                            <JobDate value={app.connected_at} />
                                        </>
                                    )}
                                    {app.renewed_at && (
                                        <>
                                            {' · '}
                                            {t('Last renewed')}{' '}
                                            <JobDate value={app.renewed_at} />
                                        </>
                                    )}
                                    {app.ends_at && (
                                        <>
                                            {' · '}
                                            {t('Ends if unused on')}{' '}
                                            <JobDate value={app.ends_at} />
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
                                                'Disconnect this app? It stops working at once and must ask to connect again.',
                                            ),
                                        )
                                    )
                                        router.delete(
                                            `/settings/connections/${app.id}`,
                                            { preserveScroll: true },
                                        );
                                }}
                            >
                                {t('Disconnect')}
                            </Button>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

AiAgents.layout = {
    breadcrumbs: [{ title: 'AI agents', href: '/settings/agents' }],
};
