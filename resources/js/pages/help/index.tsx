import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, ChevronDown, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DemoOffNote } from '@/components/demo-off';
import ElancerSiteHeader from '@/components/elancer-site-header';
import HoverFooter from '@/components/hover-footer';
import {
    foldForSearch,
    helpGroups,
    type HelpArticle,
} from '@/data/help-articles';
import { publicGuides } from '@/data/public-guides';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-guides.css';

function ArticleBody({ article }: { article: HelpArticle }) {
    const { t } = useTranslation();
    // Plan 032 part 10: the articles stay; a shared demo account has no settings to open.
    const { demo } = useDemoSession();
    const closed = demo && article.href?.startsWith('/settings');
    return (
        <>
            {article.body.map((paragraph) => (
                <p key={paragraph}>{paragraph}</p>
            ))}
            {closed && (
                <DemoOffNote>
                    {t('Settings are switched off in the shared demo.')}
                </DemoOffNote>
            )}
            {article.href && !closed && (
                <Link href={article.href} className="elancer-guide-action">
                    {article.action}
                    <ArrowUpRight size={18} aria-hidden="true" />
                </Link>
            )}
        </>
    );
}

// Plan 029: Help in the guides' frame. Search runs in the page over the titles
// and text of the language shown; nothing typed here is sent anywhere.
export default function HelpPage() {
    const { t, locale } = useTranslation();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- the articles change with the language and nothing else
    const groups = useMemo(() => helpGroups(t), [locale]);
    const [query, setQuery] = useState('');
    const [opened, setOpened] = useState<string[]>([]);

    // An address may carry a search (?q=refund) or name one article (#offers).
    useEffect(() => {
        const asked = new URLSearchParams(window.location.search).get('q');
        if (asked) setQuery(asked.slice(0, 100));
        const openNamed = () => {
            const id = decodeURIComponent(window.location.hash.slice(1));
            if (!id) return;
            setQuery('');
            setOpened((current) =>
                current.includes(id) ? current : [...current, id],
            );
            window.setTimeout(
                () =>
                    document
                        .getElementById(id)
                        ?.scrollIntoView({ block: 'start' }),
                0,
            );
        };
        openNamed();
        // An in-page visit sets the address a moment after this page appears.
        const later = window.setTimeout(openNamed, 50);
        window.addEventListener('hashchange', openNamed);
        return () => {
            window.clearTimeout(later);
            window.removeEventListener('hashchange', openNamed);
        };
    }, []);

    const search = (value: string) => {
        setQuery(value);
        const address = value.trim()
            ? '?q=' + encodeURIComponent(value.trim())
            : window.location.pathname;
        // The state object is the page router's own; only the address changes.
        window.history.replaceState(window.history.state, '', address);
    };

    const words = foldForSearch(query).split(' ').filter(Boolean);
    const searching = words.length > 0;
    const matches = useMemo(() => {
        if (!words.length) return [];
        return groups
            .flatMap((group) =>
                group.articles.map((article) => {
                    const title = foldForSearch(article.title);
                    const text =
                        title + ' ' + foldForSearch(article.body.join(' '));
                    return {
                        group,
                        article,
                        found: words.every((word) => text.includes(word)),
                        inTitle: words.every((word) => title.includes(word)),
                    };
                }),
            )
            .filter((item) => item.found)
            .sort((a, b) => Number(b.inTitle) - Number(a.inTitle));
        // eslint-disable-next-line react-hooks/exhaustive-deps -- words is rebuilt from query on every render
    }, [groups, query]);
    const total = groups.reduce((sum, group) => sum + group.articles.length, 0);

    return (
        <>
            <Head title={t('Help')}>
                <meta
                    name="description"
                    content={t(
                        'Answers about hiring, working, your account, payments and safety.',
                    )}
                />
            </Head>
            <div className="elancer-home elancer-guides">
                <a href="#main-content" className="elancer-skip-link">
                    {t('Skip to content')}
                </a>
                <ElancerSiteHeader />
                <main id="main-content" className="elancer-guide-main">
                    <header className="elancer-guide-heading">
                        <Link href="/" className="elancer-guide-eyebrow">
                            {t('Elancer home')}
                        </Link>
                        <h1>{t('Help')}</h1>
                        <p>
                            {t(
                                'Answers about hiring, working, your account, payments and safety.',
                            )}
                        </p>
                        <form
                            role="search"
                            className="elancer-help-search"
                            onSubmit={(event) => event.preventDefault()}
                        >
                            <label className="sr-only" htmlFor="help-search">
                                {t('Search Help')}
                            </label>
                            <Search size={20} aria-hidden="true" />
                            <input
                                id="help-search"
                                type="search"
                                value={query}
                                maxLength={100}
                                autoComplete="off"
                                placeholder={t(
                                    'Search Help, for example “offer” or “refund”',
                                )}
                                onChange={(event) => search(event.target.value)}
                            />
                        </form>
                        <p
                            className="elancer-help-status"
                            role="status"
                            data-test="help-status"
                        >
                            {!searching
                                ? t(':count articles in five groups', {
                                      count: total,
                                  })
                                : matches.length
                                  ? t(':count of :total articles match', {
                                        count: matches.length,
                                        total,
                                    })
                                  : t('No article matches “:query”.', {
                                        query: query.trim(),
                                    })}
                        </p>
                    </header>
                    <div className="elancer-guide-layout">
                        <aside className="elancer-guide-aside">
                            <nav aria-label={t('On this page')}>
                                <h2>{t('On this page')}</h2>
                                {searching ? (
                                    <button
                                        type="button"
                                        className="elancer-help-clear"
                                        onClick={() => search('')}
                                    >
                                        {t('Show all articles')}
                                    </button>
                                ) : (
                                    groups.map((group) => (
                                        <a href={'#' + group.id} key={group.id}>
                                            <span aria-hidden="true">
                                                {String(
                                                    group.articles.length,
                                                ).padStart(2, '0')}
                                            </span>
                                            {group.title}
                                        </a>
                                    ))
                                )}
                            </nav>
                        </aside>
                        <div className="elancer-guide-articles">
                            {searching &&
                                matches.map(({ group, article }) => (
                                    <section
                                        key={article.id}
                                        data-test="help-result"
                                        aria-labelledby={'result-' + article.id}
                                        className="elancer-help-result"
                                    >
                                        <div className="elancer-guide-section-label">
                                            <span>{group.title}</span>
                                        </div>
                                        <h2 id={'result-' + article.id}>
                                            {article.title}
                                        </h2>
                                        <ArticleBody article={article} />
                                    </section>
                                ))}
                            {searching && !matches.length && (
                                <section
                                    className="elancer-help-result"
                                    data-test="help-empty"
                                >
                                    <h2>{t('Nothing found')}</h2>
                                    <p>
                                        {t(
                                            'Try a shorter or a different word, or look through the five groups. To report something that breaks the rules, use Report on the project, profile, message or contract where it happened.',
                                        )}
                                    </p>
                                    <button
                                        type="button"
                                        className="elancer-help-clear"
                                        onClick={() => search('')}
                                    >
                                        {t('Show all articles')}
                                    </button>
                                </section>
                            )}
                            {!searching &&
                                groups.map((group) => (
                                    <section
                                        id={group.id}
                                        key={group.id}
                                        aria-labelledby={group.id + '-title'}
                                    >
                                        <h2 id={group.id + '-title'}>
                                            {group.title}
                                        </h2>
                                        <p className="elancer-guide-summary">
                                            {group.intro}
                                        </p>
                                        {group.articles.map((article) => (
                                            <details
                                                id={article.id}
                                                key={article.id}
                                                className="elancer-help-item"
                                                open={opened.includes(
                                                    article.id,
                                                )}
                                                onToggle={(event) => {
                                                    const isOpen =
                                                        event.currentTarget
                                                            .open;
                                                    setOpened((current) =>
                                                        isOpen
                                                            ? current.includes(
                                                                  article.id,
                                                              )
                                                                ? current
                                                                : [
                                                                      ...current,
                                                                      article.id,
                                                                  ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      article.id,
                                                              ),
                                                    );
                                                }}
                                            >
                                                <summary>
                                                    {article.title}
                                                    <ChevronDown
                                                        size={20}
                                                        aria-hidden="true"
                                                    />
                                                </summary>
                                                <ArticleBody
                                                    article={article}
                                                />
                                            </details>
                                        ))}
                                    </section>
                                ))}
                        </div>
                    </div>
                    <nav
                        className="elancer-guide-related"
                        aria-label={t('Explore another guide')}
                    >
                        <h2>{t('Explore another guide')}</h2>
                        <div>
                            {publicGuides(t).map((item) => (
                                <Link href={'/learn/' + item.id} key={item.id}>
                                    {item.title}
                                    <ArrowUpRight
                                        size={18}
                                        aria-hidden="true"
                                    />
                                </Link>
                            ))}
                        </div>
                    </nav>
                </main>
                <HoverFooter />
            </div>
        </>
    );
}
