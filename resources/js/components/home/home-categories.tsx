// Q33: the "What does your project need?" cards list real categories with their open-project counts.
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Braces,
    BriefcaseBusiness,
    ChartNoAxesCombined,
    Clapperboard,
    DraftingCompass,
    Megaphone,
    PenTool,
    Shapes,
    SwatchBook,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { workKind } from '@/components/home/project-art';
import type { WorkKind } from '@/components/home/project-art';
import { useTranslation } from '@/hooks/use-translation';
import type { Category } from '@/pages/discovery/shared';

const icons: Record<WorkKind, LucideIcon> = {
    development: Braces,
    design: SwatchBook,
    writing: PenTool,
    marketing: Megaphone,
    video: Clapperboard,
    data: ChartNoAxesCombined,
    business: BriefcaseBusiness,
    engineering: DraftingCompass,
    general: Shapes,
};

export default function HomeCategories({
    categories,
    total,
}: {
    categories: Category[];
    total: number;
}) {
    const { t } = useTranslation();
    // A category is the administrator's own wording; this line only describes the kind of work.
    const lines: Record<WorkKind, string> = {
        development: t('Websites, apps & clever solutions'),
        design: t('Brands people remember'),
        writing: t('Words that find their people'),
        marketing: t('Ideas that reach further'),
        video: t('Stories made to move'),
        data: t('Clarity behind the numbers'),
        business: t('Plans that hold together'),
        engineering: t('Ideas drawn to scale'),
        general: t('Good work of every kind'),
    };
    if (categories.length === 0) return null;
    return (
        <section
            id="categories"
            className="elancer-discovery-section"
            aria-labelledby="categories-heading"
        >
            <div className="elancer-section-heading">
                <div>
                    <span className="elancer-eyebrow">
                        {t('Explore by category')}
                    </span>
                    <h2 id="categories-heading">
                        {t('What does your project need?')}
                    </h2>
                </div>
                <p>
                    {t('From a first sketch to the final line of code.')}
                    <br />
                    {t('Start with the skill your idea needs.')}
                </p>
            </div>
            <div className="elancer-category-grid home-category-grid">
                {categories.map((category) => {
                    const kind = workKind(category.categoryname);
                    const Icon = icons[kind];
                    return (
                        <Link
                            key={category.id}
                            href={`/jobs?category=${encodeURIComponent(category.slug)}`}
                            className="elancer-category-card"
                        >
                            <div className="elancer-category-top">
                                <span className="elancer-category-icon">
                                    <Icon
                                        size={27}
                                        strokeWidth={1.5}
                                        aria-hidden="true"
                                    />
                                </span>
                                <span className="home-category-count">
                                    {t('Open projects: :count', {
                                        count:
                                            category.open_projects_count ?? 0,
                                    })}
                                </span>
                            </div>
                            <h3 dir="auto">{category.categoryname}</h3>
                            <p>{lines[kind]}</p>
                            <span className="elancer-category-bottom">
                                {t('Browse projects')}
                                <ArrowUpRight size={16} aria-hidden="true" />
                            </span>
                        </Link>
                    );
                })}
            </div>
            {total > categories.length && (
                <div className="elancer-talents-more">
                    <Link href="/categories" className="elancer-outline-button">
                        {t('All categories')}
                        <ArrowRight size={16} aria-hidden="true" />
                    </Link>
                </div>
            )}
        </section>
    );
}
