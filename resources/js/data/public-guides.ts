export type PublicGuide = {
    id: string;
    title: string;
    intro: string;
    sections: {
        id: string;
        title: string;
        description: string;
        points: string[];
        example?: boolean;
        href?: string;
        action?: string;
    }[];
};

export function publicGuides(t: (text: string) => string): PublicGuide[] {
    return [
        {
            id: 'how-it-works',
            title: t('How it works'),
            intro: t('From a first conversation to a clear agreement.'),
            sections: [
                {
                    id: 'hiring',
                    title: t('Hiring a freelancer'),
                    description: t(
                        'Turn your idea into a project that the right people can understand.',
                    ),
                    points: [
                        t(
                            'Describe the outcome, required skills and budget in your project draft. Review it before publishing.',
                        ),
                        t(
                            'Review proposals and portfolio samples. Compare how each freelancer approaches your requirements.',
                        ),
                        t(
                            'Start a private conversation after a proposal is submitted, then send a final offer with the agreed terms.',
                        ),
                    ],
                },
                {
                    id: 'freelancing',
                    title: t('Working as a freelancer'),
                    description: t(
                        'Show your experience and apply for work that fits your skills.',
                    ),
                    points: [
                        t(
                            'Complete onboarding and your profile, add your skills and portfolio, then publish your profile when it is ready.',
                        ),
                        t(
                            'Read the project brief carefully. Explain your approach, ask specific questions and propose a realistic price.',
                        ),
                        t(
                            'Review the final offer before accepting. Request changes if the scope, price or delivery duration needs adjustment.',
                        ),
                    ],
                },
                {
                    id: 'agreements',
                    title: t('Offers and agreements'),
                    description: t(
                        'Keep the work, price and expectations in one agreed record.',
                    ),
                    points: [
                        t(
                            'A final offer records the scope, named deliverables, fixed USD price, calendar-day duration and included revision rounds.',
                        ),
                        t(
                            'An offer lasts 72 hours. A client can have one pending offer per project; a replacement creates a separate history entry.',
                        ),
                        t(
                            'Acceptance preserves an agreement snapshot and closes hiring for that project. Contract messages remain available to its participants.',
                        ),
                        t(
                            'Funding is not available yet. Accepted contracts await funding, and the delivery clock has not started.',
                        ),
                    ],
                },
                {
                    id: 'faq',
                    title: t('Frequently asked questions'),
                    description: t('A few useful answers before you start.'),
                    points: [
                        t(
                            'Can I hire and freelance with one account? Yes. Switch your workspace for the task you want to do; access still depends on your role in each project.',
                        ),
                        t(
                            'Does saving my profile publish it? No. Profile publication is a separate action after the required information is complete.',
                        ),
                        t(
                            'Can I change an accepted agreement? Its recorded terms are preserved. Resolve questions in contract messages rather than assuming the agreement has changed.',
                        ),
                    ],
                },
            ],
        },
        {
            id: 'project-guides',
            title: t('Project guides'),
            intro: t('A little preparation makes proposals easier to compare.'),
            sections: [
                {
                    id: 'brief',
                    title: t('Write a project brief'),
                    description: t(
                        'Explain the result you need and who it is for.',
                    ),
                    points: [
                        t(
                            'Start with the problem: who is affected, what is missing today and what a successful outcome would change.',
                        ),
                        t(
                            'List existing assets, required integrations and constraints. Share references and explain what you like about them.',
                        ),
                        t(
                            'Example brief: a bilingual five-page website for a local studio, with service pages, a contact form and editable content.',
                        ),
                    ],
                },
                {
                    id: 'deliverables',
                    title: t('Define deliverables'),
                    description: t(
                        'Make completion something both sides can check.',
                    ),
                    points: [
                        t(
                            'Name each item: pages, screens, files, source code or documentation. Specify formats and what must be editable.',
                        ),
                        t(
                            'Describe acceptance criteria, such as supported screen sizes, languages and the main tasks a user must complete.',
                        ),
                        t(
                            'State what is excluded and how many revision rounds are included. Separate corrections from additional features.',
                        ),
                    ],
                },
                {
                    id: 'budget',
                    title: t('Set a budget'),
                    description: t(
                        'Connect your budget to scope and priorities.',
                    ),
                    points: [
                        t(
                            'Separate essential deliverables from optional improvements. Reduce scope if your budget cannot cover both.',
                        ),
                        t(
                            'Account for complexity, research, revisions and handover. Ask freelancers to explain what their quoted price includes.',
                        ),
                        t(
                            'Project budgets are guidance; proposals may differ. The accepted final offer fixes the agreed price in USD.',
                        ),
                    ],
                },
                {
                    id: 'proposals',
                    title: t('Compare proposals'),
                    description: t(
                        'Look for understanding as well as a price.',
                    ),
                    points: [
                        t(
                            'Compare relevant experience, the proposed approach, availability and how clearly the freelancer addresses your brief.',
                        ),
                        t(
                            'Ask shortlisted applicants the same key questions so you can compare answers fairly.',
                        ),
                        t(
                            'Clarify ownership of final files, handover expectations and revisions before sending the final offer.',
                        ),
                    ],
                },
            ],
        },
        {
            id: 'inspiration',
            title: t('Get inspired'),
            intro: t(
                'Starting points for your next brief, ready to adapt to your needs.',
            ),
            sections: [
                {
                    id: 'websites',
                    title: t('Website examples'),
                    description: t(
                        'A bilingual website for an independent studio.',
                    ),
                    points: [
                        t(
                            'Give visitors a clear introduction, a small selection of work, service details and a simple way to get in touch.',
                        ),
                        t(
                            'Possible deliverables: a responsive five-page site, editable project entries, a contact form and a short handover guide.',
                        ),
                    ],
                    example: true,
                },
                {
                    id: 'branding',
                    title: t('Brand identities'),
                    description: t(
                        'A consistent visual identity for a neighbourhood cafe.',
                    ),
                    points: [
                        t(
                            'Choose the audience, personality and places the identity will appear before deciding on a visual direction.',
                        ),
                        t(
                            'Possible deliverables: logo variations, colour and typography guidance, menu layout and reusable social post templates.',
                        ),
                    ],
                    example: true,
                },
                {
                    id: 'apps',
                    title: t('App designs'),
                    description: t(
                        'An appointment-booking experience for a small service business.',
                    ),
                    points: [
                        t(
                            'Map the journey from choosing a service to selecting a time and receiving confirmation. Include empty and error states.',
                        ),
                        t(
                            'Possible deliverables: user flows, a clickable prototype, mobile screen designs and a reusable component library.',
                        ),
                    ],
                    example: true,
                },
                {
                    id: 'portfolios',
                    title: t('Featured portfolios'),
                    description: t(
                        'Meet people through the work they choose to share.',
                    ),
                    points: [
                        t(
                            'No portfolios have been selected for this showcase yet. Explore published freelancer profiles to see the work available now.',
                        ),
                        t(
                            'A future feature will credit its creator and use work approved for publication. The examples above are editorial ideas, not completed client projects.',
                        ),
                    ],
                    href: '/freelancers',
                    action: t('Explore freelancer profiles'),
                },
            ],
        },
        {
            id: 'why-elancer',
            title: t('Why Elancer'),
            intro: t(
                'A shared place for independent skills and well-defined projects.',
            ),
            sections: [
                {
                    id: 'about',
                    title: t('About the platform'),
                    description: t(
                        'Built around clear communication and fixed-price project agreements.',
                    ),
                    points: [
                        t(
                            'Clients describe projects and review proposals. Freelancers publish their experience and choose work suited to their skills.',
                        ),
                        t(
                            'English and Arabic interfaces support the same workflows. One account can use both client and freelancer workspaces.',
                        ),
                    ],
                },
                {
                    id: 'collaboration',
                    title: t('Collaboration tools'),
                    description: t(
                        'Keep hiring discussions close to the proposal and agreement.',
                    ),
                    points: [
                        t(
                            'Clients can invite freelancers, shortlist proposals and compare applicants before choosing who to work with.',
                        ),
                        t(
                            'Private hiring conversations connect project participants. Accepted contracts keep their own conversation and agreement record.',
                        ),
                        t(
                            'Formal delivery, payment and revision workflows are still upcoming. Current contract screens show when funding has not started.',
                        ),
                    ],
                },
                {
                    id: 'agreement-record',
                    title: t('How agreements work'),
                    description: t(
                        'Make the final terms explicit before accepting.',
                    ),
                    points: [
                        t(
                            'A proposal starts the discussion. A final offer records the exact scope, price, duration and revisions the two sides agree to.',
                        ),
                        t(
                            'Acceptance saves a fixed copy of those terms. A later profile edit does not rewrite the recorded agreement.',
                        ),
                    ],
                    href: '/learn/how-it-works#agreements',
                    action: t('Read the agreement guide'),
                },
                {
                    id: 'stories',
                    title: t('Success stories'),
                    description: t('Real experiences will belong here.'),
                    points: [
                        t(
                            'We have not published verified customer success stories yet. We will share them with the participants’ permission when they are available.',
                        ),
                        t(
                            'Until then, explore sample project ideas or published profiles without treating them as customer endorsements.',
                        ),
                    ],
                    href: '/learn/inspiration',
                    action: t('Explore project ideas'),
                },
            ],
        },
        {
            id: 'resources',
            title: t('Resources'),
            intro: t('Practical guidance for both sides of a project.'),
            sections: [
                {
                    id: 'clients',
                    title: t('Client guide'),
                    description: t(
                        'Prepare the details that make a collaboration easier.',
                    ),
                    points: [
                        t(
                            'Write the brief, decide which deliverables matter most and identify the assets you can provide.',
                        ),
                        t(
                            'Compare proposals, clarify questions in messages and review every term before sending a final offer.',
                        ),
                    ],
                    href: '/learn/project-guides',
                    action: t('Open the project guides'),
                },
                {
                    id: 'freelancers',
                    title: t('Freelancer guide'),
                    description: t('Make your experience easy to understand.'),
                    points: [
                        t(
                            'Use a specific headline and explain your contribution to portfolio work. Only publish material you have permission to share.',
                        ),
                        t(
                            'Tailor each proposal to the brief. State assumptions, realistic timing and what is included in your price.',
                        ),
                        t(
                            'Read final offers carefully. Ask for changes before acceptance when expectations do not match.',
                        ),
                    ],
                    href: '/learn/how-it-works#freelancing',
                    action: t('Read the freelancer walkthrough'),
                },
                {
                    id: 'help',
                    title: t('Help centre'),
                    description: t(
                        'Start with the common questions and account controls.',
                    ),
                    points: [
                        t(
                            'For access problems, use the password reset option on the login page or your connected sign-in provider.',
                        ),
                        t(
                            'Language and appearance controls are in the navigation. Account and security options are available in Settings after sign-in.',
                        ),
                        t(
                            'For project questions, keep the discussion in the relevant conversation so both participants can refer to it. This page is a self-service guide, not a support ticket form.',
                        ),
                    ],
                    href: '/learn/how-it-works#faq',
                    action: t('Read common questions'),
                },
                {
                    id: 'updates',
                    title: t('Platform updates'),
                    description: t(
                        'What is available, and what is still being built.',
                    ),
                    points: [
                        t(
                            'September 2026: profiles, project discovery, proposals, invitations and private hiring conversations are available.',
                        ),
                        t(
                            'Google and GitHub sign-in, final offers and recorded contract agreements have been added.',
                        ),
                        t(
                            'Coming later: sandbox funding and formal delivery workflows. No real payment collection is available in the current project.',
                        ),
                    ],
                },
            ],
        },
    ];
}
