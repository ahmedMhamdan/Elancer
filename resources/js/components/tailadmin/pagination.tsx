// No table pagination exists in Ahmed's local TailAdmin source (checked 2026-09-12).
// Elancer extension using its adapted Button; server URLs preserve filters/search.
import { router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import Button from './button';

export type PaginationData = {
    current_page: number;
    last_page: number;
    total: number;
    path: string;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function Pagination({
    data,
    onNavigate,
}: {
    data: PaginationData;
    onNavigate?: () => void;
}) {
    const page = usePage();
    const ar = page.props.auth.user.locale === 'ar';
    const numbers = [
        ...new Set([
            1,
            data.current_page - 1,
            data.current_page,
            data.current_page + 1,
            data.last_page,
        ]),
    ]
        .filter((n) => n >= 1 && n <= data.last_page)
        .sort((a, b) => a - b);
    function visit(url: string | null) {
        if (!url) return;
        onNavigate?.();
        router.get(url, {}, { preserveScroll: true });
    }
    function urlFor(number: number) {
        const query = new URLSearchParams(page.url.split('?')[1] || '');
        query.set('page', String(number));
        return `${data.path}?${query.toString()}`;
    }
    return (
        <nav
            aria-label={ar ? 'ترقيم الصفحات' : 'Pagination'}
            dir={ar ? 'rtl' : 'ltr'}
            className="border-border bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
        >
            <p className="text-muted-foreground text-xs">
                {ar ? 'الصفحة' : 'Page'} {data.current_page} / {data.last_page}{' '}
                · {data.total} {ar ? 'نتيجة' : 'results'}
            </p>
            <div className="flex max-w-full flex-wrap items-center gap-1">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!data.prev_page_url}
                    onClick={() => visit(data.prev_page_url)}
                    aria-label={ar ? 'السابق' : 'Previous'}
                >
                    <ChevronLeft
                        className="size-4 rtl:rotate-180"
                        aria-hidden="true"
                    />
                </Button>
                {numbers.map((number, index) => (
                    <span
                        key={number}
                        className="inline-flex items-center gap-1"
                    >
                        {index > 0 && number - numbers[index - 1] > 1 && (
                            <span className="text-muted-foreground px-1">
                                …
                            </span>
                        )}
                        <Button
                            size="sm"
                            className="min-w-11"
                            variant={
                                number === data.current_page
                                    ? 'primary'
                                    : 'outline'
                            }
                            aria-current={
                                number === data.current_page
                                    ? 'page'
                                    : undefined
                            }
                            aria-label={`${ar ? 'الصفحة' : 'Page'} ${number}`}
                            onClick={() => visit(urlFor(number))}
                        >
                            {number}
                        </Button>
                    </span>
                ))}
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!data.next_page_url}
                    onClick={() => visit(data.next_page_url)}
                    aria-label={ar ? 'التالي' : 'Next'}
                >
                    <ChevronRight
                        className="size-4 rtl:rotate-180"
                        aria-hidden="true"
                    />
                </Button>
            </div>
        </nav>
    );
}
