import {
    ArrowDown,
    ArrowRight,
    ArrowUpRight,
    Check,
    Plus,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
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
                            A WORLD OF WHAT YOU CAN DO
                        </span>
                        <h2 id="categories-heading">
                            Find the right kind
                            <br />
                            of <span>brilliant.</span>
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
                        ({ id, icon: Icon, title, description }, index) => (
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
                                    <span className="elancer-category-index">
                                        0{index + 1}
                                    </span>
                                </div>
                                <h3>{title}</h3>
                                <p>{description}</p>
                                <span className="elancer-category-bottom">
                                    {category === id
                                        ? 'Selected'
                                        : 'Explore the possibilities'}
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
                            THE PEOPLE BEHIND THE POSSIBILITIES
                        </span>
                        <h2 id="talent-heading">
                            Independent minds.
                            <br />
                            <span>Remarkable potential.</span>
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
                            ? `${selected.title} · ${visible.length} sample profile`
                            : `Showing ${visible.length} sample profiles`}
                    </span>
                    {category && (
                        <button type="button" onClick={() => setCategory(null)}>
                            Show all categories{' '}
                            <ArrowRight size={14} aria-hidden="true" />
                        </button>
                    )}
                </div>
                <div className="elancer-talent-grid">
                    {visible.map((person) => (
                        <FreelancerCard key={person.id} freelancer={person} />
                    ))}
                </div>
                {!category && (
                    <div className="elancer-talents-more">
                        <button
                            type="button"
                            className="elancer-outline-button"
                            aria-expanded={showAll}
                            onClick={() => setShowAll((current) => !current)}
                        >
                            {showAll
                                ? 'Show fewer profiles'
                                : 'Meet more sample talent'}
                            <ArrowDown
                                size={16}
                                className={showAll ? 'rotate-180' : ''}
                                aria-hidden="true"
                            />
                        </button>
                    </div>
                )}
            </section>
        </>
    );
}
