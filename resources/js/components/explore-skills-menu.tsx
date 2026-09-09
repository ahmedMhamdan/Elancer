import {
    ArrowRight,
    ChevronDown,
    ChevronRight,
    Code2,
    PenLine,
    Shapes,
} from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useEffect, useId, useRef, useState } from 'react';
import { home } from '@/routes';

const categories = [
    {
        name: 'Development & IT',
        icon: Code2,
        title: 'Build something that works.',
        description: 'Websites, applications, and the people behind them.',
        skills: [
            'Web development',
            'Mobile applications',
            'E-commerce',
            'APIs & integrations',
        ],
    },
    {
        name: 'Design & creative',
        icon: Shapes,
        title: 'Give your idea its own identity.',
        description: 'Thoughtful visuals for brands and digital products.',
        skills: [
            'Brand identity',
            'UI / UX design',
            'Illustration',
            'Presentation design',
        ],
    },
    {
        name: 'Writing & content',
        icon: PenLine,
        title: 'Find the right words.',
        description: 'Clear stories that connect with your audience.',
        skills: [
            'Copywriting',
            'Website content',
            'Translation',
            'Content strategy',
        ],
    },
];

export default function ExploreSkillsMenu({
    mobile = false,
    onNavigate,
}: {
    mobile?: boolean;
    onNavigate?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const root = useRef<HTMLDivElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const id = useId();
    const reduced = useReducedMotion();
    const category = categories[active];
    const navigate = () => {
        setOpen(false);
        onNavigate?.();
    };

    useEffect(() => {
        if (!open) return;
        const outside = (event: PointerEvent) => {
            if (
                event.target instanceof Node &&
                !root.current?.contains(event.target)
            )
                setOpen(false);
        };
        const breakpoint = window.matchMedia('(min-width: 1024px)');
        const close = () => setOpen(false);
        document.addEventListener('pointerdown', outside);
        breakpoint.addEventListener('change', close);
        return () => {
            document.removeEventListener('pointerdown', outside);
            breakpoint.removeEventListener('change', close);
        };
    }, [open]);

    return (
        <div
            ref={root}
            className={
                mobile
                    ? 'elancer-explore elancer-explore-mobile'
                    : 'elancer-explore'
            }
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget))
                    setOpen(false);
            }}
            onKeyDown={(event) => {
                if (event.key === 'Escape' && open) {
                    event.preventDefault();
                    event.stopPropagation();
                    setOpen(false);
                    trigger.current?.focus();
                }
            }}
        >
            <button
                ref={trigger}
                type="button"
                className="elancer-resizable-link elancer-explore-trigger"
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen(!open)}
            >
                Explore skills{' '}
                <ChevronDown
                    size={15}
                    aria-hidden="true"
                    className={open ? 'is-open' : ''}
                />
            </button>
            <AnimatePresence>
                {open && (
                    <motion.div
                        id={id}
                        className="elancer-explore-panel"
                        initial={{
                            opacity: 0,
                            y: reduced ? 0 : -12,
                            scale: reduced ? 1 : 0.98,
                        }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{
                            opacity: 0,
                            y: reduced ? 0 : -6,
                            transition: { duration: reduced ? 0 : 0.14 },
                        }}
                        transition={{
                            duration: reduced ? 0 : 0.24,
                            ease: [0.22, 1, 0.36, 1],
                        }}
                    >
                        <div className="elancer-explore-layout">
                            <div
                                className="elancer-explore-categories"
                                role="group"
                                aria-label="Skill categories"
                            >
                                <p>Explore by category</p>
                                {categories.map((item, index) => (
                                    <button
                                        key={item.name}
                                        type="button"
                                        aria-pressed={active === index}
                                        aria-controls={`${id}-content`}
                                        onClick={() => setActive(index)}
                                    >
                                        <item.icon
                                            size={18}
                                            aria-hidden="true"
                                        />
                                        <span>{item.name}</span>
                                        <ChevronRight
                                            size={15}
                                            aria-hidden="true"
                                        />
                                    </button>
                                ))}
                            </div>
                            <div
                                id={`${id}-content`}
                                className="elancer-explore-content"
                                aria-live="polite"
                            >
                                <motion.div
                                    key={active}
                                    initial={{ opacity: 0, x: reduced ? 0 : 8 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    transition={{ duration: reduced ? 0 : 0.2 }}
                                >
                                    <h2>{category.title}</h2>
                                    <p>{category.description}</p>
                                    <ul className="elancer-explore-skills">
                                        {category.skills.map((skill) => (
                                            <li key={skill}>{skill}</li>
                                        ))}
                                    </ul>
                                    <a
                                        href={`${home().url}#featured-freelancers`}
                                        onClick={navigate}
                                    >
                                        Meet sample talent{' '}
                                        <ArrowRight
                                            size={16}
                                            aria-hidden="true"
                                        />
                                    </a>
                                </motion.div>
                            </div>
                        </div>
                        <div className="elancer-explore-footer">
                            <a
                                href={`${home().url}#categories`}
                                onClick={navigate}
                            >
                                See all skills{' '}
                                <ArrowRight size={16} aria-hidden="true" />
                            </a>
                            <a
                                href={`${home().url}#how-it-works`}
                                onClick={navigate}
                            >
                                How Elancer works
                            </a>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
