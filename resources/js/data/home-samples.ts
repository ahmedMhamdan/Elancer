import {
    Braces,
    ChartNoAxesCombined,
    Clapperboard,
    Megaphone,
    PenTool,
    SwatchBook,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type CategoryId =
    | 'development'
    | 'design'
    | 'writing'
    | 'marketing'
    | 'video'
    | 'data';
export type PreviewStyle =
    | 'website'
    | 'brand'
    | 'editorial'
    | 'campaign'
    | 'film'
    | 'analytics';

export const homeCategories: {
    id: CategoryId;
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        id: 'development',
        title: 'Development & IT',
        description: 'Websites, apps & clever solutions',
        icon: Braces,
    },
    {
        id: 'design',
        title: 'Design & creative',
        description: 'Brands people remember',
        icon: SwatchBook,
    },
    {
        id: 'writing',
        title: 'Writing & translation',
        description: 'Words that find their people',
        icon: PenTool,
    },
    {
        id: 'marketing',
        title: 'Digital marketing',
        description: 'Ideas that reach further',
        icon: Megaphone,
    },
    {
        id: 'video',
        title: 'Video & animation',
        description: 'Stories made to move',
        icon: Clapperboard,
    },
    {
        id: 'data',
        title: 'Data & analytics',
        description: 'Clarity behind the numbers',
        icon: ChartNoAxesCombined,
    },
];

export type SampleFreelancer = {
    id: string;
    name: string;
    initials: string;
    role: string;
    category: CategoryId;
    skills: string[];
    intro: string;
    project: string;
    projectDescription: string;
    preview: PreviewStyle;
};

// Fictional display data only. These are not registered users or live offers.
export const sampleFreelancers: SampleFreelancer[] = [
    {
        id: 'sami',
        name: 'Sami Nasser',
        initials: 'SN',
        role: 'Full-stack developer',
        category: 'development',
        skills: ['Laravel', 'React', 'MySQL'],
        preview: 'website',
        intro: 'Thoughtful web applications, from the first wireframe to the final interaction.',
        project: 'Haven — a quieter kind of commerce',
        projectDescription:
            'An illustrative storefront concept with a simple catalog, thoughtful product pages, and a calm shopping experience.',
    },
    {
        id: 'lina',
        name: 'Lina Khalil',
        initials: 'LK',
        role: 'Brand & product designer',
        category: 'design',
        skills: ['Brand identity', 'Figma', 'UI/UX'],
        preview: 'brand',
        intro: 'Distinctive identities and digital experiences with a little personality and a lot of purpose.',
        project: 'Forma — identity in good shape',
        projectDescription:
            'A fictional identity study exploring an expressive wordmark, a flexible visual system, and a tactile green palette.',
    },
    {
        id: 'noor',
        name: 'Noor Mansour',
        initials: 'NM',
        role: 'Writer & content strategist',
        category: 'writing',
        skills: ['Copywriting', 'Arabic', 'English'],
        preview: 'editorial',
        intro: 'Clear, human words that turn complicated ideas into stories people want to read.',
        project: 'Fieldnotes — stories worth keeping',
        projectDescription:
            'An illustrative editorial concept combining long-form stories, bilingual content direction, and clear information hierarchy.',
    },
    {
        id: 'omar',
        name: 'Omar Saleh',
        initials: 'OS',
        role: 'Digital marketing strategist',
        category: 'marketing',
        skills: ['Content strategy', 'SEO', 'Campaigns'],
        preview: 'campaign',
        intro: 'Connecting a strong message with the people who need to hear it.',
        project: 'Grow — a fresh campaign direction',
        projectDescription:
            'A fictional campaign concept for a small sustainable business, from message planning to a coordinated launch.',
    },
    {
        id: 'maya',
        name: 'Maya Haddad',
        initials: 'MH',
        role: 'Motion designer',
        category: 'video',
        skills: ['Motion graphics', 'Animation', 'Storyboards'],
        preview: 'film',
        intro: 'Small details, expressive movement, and stories that stay with you.',
        project: 'Still / Motion — a title sequence',
        projectDescription:
            'An illustrative motion study built around geometric forms, changing rhythm, and clean editorial typography.',
    },
    {
        id: 'adam',
        name: 'Adam Rami',
        initials: 'AR',
        role: 'Data analyst',
        category: 'data',
        skills: ['SQL', 'Python', 'Dashboards'],
        preview: 'analytics',
        intro: 'Making complex information understandable, useful, and ready for a decision.',
        project: 'Clarity — information with direction',
        projectDescription:
            'A fictional dashboard concept for understanding trends. All shapes and values in this preview are illustrative.',
    },
];
