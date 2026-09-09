import {
    ArrowDown,
    ArrowRight,
    ArrowUpRight,
    Check,
    Plus,
    SlidersHorizontal,
} from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { homeCategories, sampleFreelancers } from '@/data/home-samples';
import type { CategoryId, SampleFreelancer } from '@/data/home-samples';

function PortfolioPreview({ freelancer }: { freelancer: SampleFreelancer }) {
    return (
        <div
            className={`elancer-portfolio-preview elancer-preview-${freelancer.preview}`}
            role="img"
            aria-label={`Illustrative portfolio concept: ${freelancer.project}`}
        >
            <span className="elancer-preview-caption">
                SELECTED CONCEPT / 0{sampleFreelancers.indexOf(freelancer) + 1}
            </span>
            {freelancer.preview === 'website' && (
                <div className="elancer-mini-browser">
                    <div className="elancer-mini-nav">
                        <b>haven.</b>
                        <span>OBJECTS FOR EVERYDAY</span>
                        <Plus size={10} />
                    </div>
                    <div className="elancer-mini-store">
                        <div>
                            <small>LESS, BUT BETTER.</small>
                            <strong>
                                A little space
                                <br />
                                to slow down.
                            </strong>
                            <span className="elancer-mini-cta">
                                Explore the collection ↗
                            </span>
                        </div>
                        <div className="elancer-vase" />
                    </div>
                </div>
            )}
            {freelancer.preview === 'brand' && (
                <div className="elancer-brand-concept">
                    <div className="elancer-forma-symbol">
                        <span />
                        <span />
                        <span />
                        <span />
                    </div>
                    <strong>
                        forma<span>®</span>
                    </strong>
                    <small>ROOM TO BECOME.</small>
                </div>
            )}
            {freelancer.preview === 'editorial' && (
                <div className="elancer-editorial-concept">
                    <small>VOL. 01 / THE EVERYDAY ISSUE</small>
                    <strong>
                        Field
                        <br />
                        notes<span>✳</span>
                    </strong>
                    <span>Good stories take root.</span>
                </div>
            )}
            {freelancer.preview === 'campaign' && (
                <div className="elancer-campaign-concept">
                    <span>SMALL STEPS. BIG DIFFERENCE.</span>
                    <strong>
                        Good things
                        <br />
                        <em>grow.</em>
                    </strong>
                    <div className="elancer-leaf-mark" />
                </div>
            )}
            {freelancer.preview === 'film' && (
                <div className="elancer-film-concept">
                    <div className="elancer-film-orbit" />
                    <strong>
                        still /<br />
                        <span>motion</span>
                    </strong>
                    <small>A STUDY IN POSSIBILITY</small>
                </div>
            )}
            {freelancer.preview === 'analytics' && (
                <div className="elancer-analytics-concept">
                    <strong>
                        clarity<span>↗</span>
                    </strong>
                    <span>THE BIG PICTURE</span>
                    <div className="elancer-chart-bars">
                        {[40, 65, 48, 76, 60, 90, 80].map((height, index) => (
                            <i key={index} style={{ height: `${height}%` }} />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function FreelancerCard({ freelancer }: { freelancer: SampleFreelancer }) {
    return (
        <article className="elancer-talent-card">
            <PortfolioPreview freelancer={freelancer} />
            <div className="elancer-talent-body">
                <div className="elancer-talent-identity">
                    <span className="elancer-avatar" aria-hidden="true">
                        {freelancer.initials}
                    </span>
                    <div>
                        <h3>{freelancer.name}</h3>
                        <p>{freelancer.role}</p>
                    </div>
                    <span className="elancer-sample-label">Sample</span>
                </div>
                <p className="elancer-talent-intro">{freelancer.intro}</p>
                <ul
                    className="elancer-skill-tags"
                    aria-label={`${freelancer.name}'s skills`}
                >
                    {freelancer.skills.map((skill) => (
                        <li key={skill}>{skill}</li>
                    ))}
                </ul>
                <Dialog>
                    <DialogTrigger asChild>
                        <button
                            className="elancer-profile-trigger"
                            type="button"
                            aria-label={`View sample profile: ${freelancer.name}`}
                        >
                            View sample profile{' '}
                            <ArrowUpRight size={16} aria-hidden="true" />
                        </button>
                    </DialogTrigger>
                    <DialogContent className="elancer-profile-dialog">
                        <DialogHeader>
                            <span className="elancer-sample-label">
                                Fictional sample profile
                            </span>
                            <DialogTitle>{freelancer.name}</DialogTitle>
                            <DialogDescription>
                                {freelancer.role}. {freelancer.intro}
                            </DialogDescription>
                        </DialogHeader>
                        <PortfolioPreview freelancer={freelancer} />
                        <div>
                            <h3>{freelancer.project}</h3>
                            <p>{freelancer.projectDescription}</p>
                        </div>
                        <ul className="elancer-skill-tags" aria-label="Skills">
                            {freelancer.skills.map((skill) => (
                                <li key={skill}>{skill}</li>
                            ))}
                        </ul>
                        <p className="elancer-dialog-note">
                            This profile and portfolio are fictional examples,
                            not a real person or an offer for hire.
                        </p>
                    </DialogContent>
                </Dialog>
            </div>
        </article>
    );
}

export default function HomeMarketplace() {
    const [category, setCategory] = useState<CategoryId | null>(null);
    const [showAll, setShowAll] = useState(false);
    const reducedMotion = useReducedMotion();
    const gridRef = useRef<HTMLDivElement>(null);
    const [gridHeight, setGridHeight] = useState<number>();

    useEffect(() => {
        const grid = gridRef.current;
        if (!grid) return;
        const observer = new ResizeObserver(([entry]) => {
            setGridHeight(entry.contentRect.height);
        });
        observer.observe(grid);
        return () => observer.disconnect();
    }, []);
    const selected = homeCategories.find((item) => item.id === category);
    const matching = category
        ? sampleFreelancers.filter((person) => person.category === category)
        : sampleFreelancers;
    const visible = category || showAll ? matching : matching.slice(0, 3);

    const selectCategory = (id: CategoryId) => {
        setCategory((current) => (current === id ? null : id));
        setShowAll(false);
    };

    return (
        <>
            <section
                id="categories"
                className="elancer-discovery-section"
                aria-labelledby="categories-heading"
            >
                <div className="elancer-section-heading">
                    <div>
                        <span className="elancer-eyebrow">
                            Explore by skill
                        </span>
                        <h2 id="categories-heading">
                            What does your project need?
                        </h2>
                    </div>
                    <p>
                        From a first sketch to the final line of code.
                        <br />
                        Start with the skill your idea needs.
                    </p>
                </div>
                <div className="elancer-category-grid">
                    {homeCategories.map(
                        ({ id, icon: Icon, title, description }) => (
                            <button
                                key={id}
                                type="button"
                                className="elancer-category-card"
                                aria-pressed={category === id}
                                onClick={() => selectCategory(id)}
                                aria-controls="featured-freelancers"
                            >
                                <div className="elancer-category-top">
                                    <span className="elancer-category-icon">
                                        <Icon
                                            size={27}
                                            strokeWidth={1.5}
                                            aria-hidden="true"
                                        />
                                    </span>
                                </div>
                                <h3>{title}</h3>
                                <p>{description}</p>
                                <span className="elancer-category-bottom">
                                    {category === id
                                        ? 'Selected'
                                        : 'View sample talent'}
                                    {category === id ? (
                                        <Check size={16} aria-hidden="true" />
                                    ) : (
                                        <ArrowUpRight
                                            size={16}
                                            aria-hidden="true"
                                        />
                                    )}
                                </span>
                            </button>
                        ),
                    )}
                </div>
                <p className="elancer-category-help">
                    <SlidersHorizontal size={13} aria-hidden="true" /> Select a
                    category to explore the sample talent below.
                </p>
            </section>

            <section
                id="featured-freelancers"
                className="elancer-talent-section"
                aria-labelledby="talent-heading"
            >
                <div className="elancer-section-heading">
                    <div>
                        <span className="elancer-eyebrow">
                            A closer look at the work
                        </span>
                        <h2 id="talent-heading">
                            Meet the skills behind the work.
                        </h2>
                    </div>
                    <div className="elancer-talent-heading-note">
                        <span className="elancer-sample-label">
                            Sample talent showcase
                        </span>
                        <p>
                            Different strengths. A shared love for the craft.
                            <br />
                            Explore these fictional profiles and portfolio
                            concepts.
                        </p>
                    </div>
                </div>
                <div className="elancer-talent-filter">
                    <span role="status" aria-live="polite">
                        {selected
                            ? `${selected.title}: ${visible.length} sample ${visible.length === 1 ? 'profile' : 'profiles'}`
                            : `Showing ${visible.length} sample profiles`}
                    </span>
                    {category && (
                        <button type="button" onClick={() => setCategory(null)}>
                            Show all categories{' '}
                            <ArrowRight size={14} aria-hidden="true" />
                        </button>
                    )}
                </div>
                <motion.div
                    className="elancer-talent-reveal"
                    initial={false}
                    animate={{
                        height: reducedMotion ? 'auto' : (gridHeight ?? 'auto'),
                    }}
                    transition={{
                        duration: reducedMotion ? 0 : 0.48,
                        ease: [0.22, 1, 0.36, 1],
                    }}
                >
                    <div
                        ref={gridRef}
                        id="elancer-talent-results"
                        className="elancer-talent-grid"
                    >
                        <AnimatePresence initial={false} mode="popLayout">
                            {visible.map((person, index) => (
                                <motion.div
                                    key={person.id}
                                    className="elancer-talent-reveal-item"
                                    layout={reducedMotion ? false : 'position'}
                                    initial={{
                                        opacity: 0,
                                        y: reducedMotion ? 0 : 20,
                                    }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{
                                        opacity: 0,
                                        y: reducedMotion ? 0 : 8,
                                        transition: {
                                            duration: reducedMotion ? 0 : 0.15,
                                        },
                                    }}
                                    transition={{
                                        duration: reducedMotion ? 0 : 0.32,
                                        delay: reducedMotion
                                            ? 0
                                            : Math.max(0, index - 3) * 0.06,
                                        ease: [0.22, 1, 0.36, 1],
                                    }}
                                >
                                    <FreelancerCard freelancer={person} />
                                </motion.div>
                            ))}
                        </AnimatePresence>
                    </div>
                </motion.div>
                {!category && (
                    <div className="elancer-talents-more">
                        <button
                            type="button"
                            className="elancer-outline-button"
                            aria-expanded={showAll}
                            aria-controls="elancer-talent-results"
                            onClick={() => setShowAll((current) => !current)}
                        >
                            {showAll
                                ? 'Show fewer profiles'
                                : 'Meet more sample talent'}
                            <ArrowDown
                                size={16}
                                className={
                                    showAll
                                        ? 'elancer-talents-chevron rotate-180'
                                        : 'elancer-talents-chevron'
                                }
                                aria-hidden="true"
                            />
                        </button>
                    </div>
                )}
            </section>
        </>
    );
}
