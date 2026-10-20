// Plan 029: the Help articles. Each sentence is an interface string, so its Arabic
// lives in translations.ts and the translation check refuses one without it.
// Every statement here describes what the application does today; a slice that
// changes a rule changes its article with it.
export type HelpArticle = {
    id: string;
    title: string;
    body: string[];
    href?: string;
    action?: string;
};

export type HelpGroup = {
    id: 'hiring' | 'working' | 'accounts' | 'payments' | 'safety';
    title: string;
    intro: string;
    articles: HelpArticle[];
};

export function helpGroups(t: (text: string) => string): HelpGroup[] {
    return [
        {
            id: 'hiring',
            title: t('Hiring'),
            intro: t(
                'Posting a project, choosing a freelancer and agreeing the terms.',
            ),
            articles: [
                {
                    id: 'posting',
                    title: t('Posting a project'),
                    body: [
                        t(
                            'Start a draft from My projects and fill it in step by step: the brief, the category and skills, the budget, the date applications close and up to three questions for applicants. The draft saves by itself and only you can see it.',
                        ),
                        t(
                            'Projects are fixed price. Give one target amount or a range in US dollars; freelancers may propose more or less.',
                        ),
                        t(
                            'Review the draft, then publish it. A published project appears in Find work and takes applications until its closing date.',
                        ),
                    ],
                    href: '/my-projects',
                    action: t('Open My projects'),
                },
                {
                    id: 'changing-project',
                    title: t('Changing a project after publishing'),
                    body: [
                        t(
                            'The published brief stays as written, so every applicant answers the same text. To add or correct something, post a clarification: a dated public note under the brief. Freelancers who already applied are told.',
                        ),
                        t(
                            'You can move the closing date later, which reopens applications if it had passed. Files are attached while the project is still a draft: up to three, 5 MB each.',
                        ),
                    ],
                    href: '/my-projects',
                    action: t('Open My projects'),
                },
                {
                    id: 'proposals-review',
                    title: t('Reviewing proposals'),
                    body: [
                        t(
                            'Open a project and choose Review applicants to read each proposal: the price, the delivery time, the cover message, the answers to your questions and the work samples.',
                        ),
                        t(
                            'Mark applicants as shortlisted or archived and keep private notes; only you see them. Select up to three proposals to compare them side by side.',
                        ),
                        t(
                            'A proposal you decline shows as declined to its author, and you can reopen it later.',
                        ),
                    ],
                    href: '/my-projects',
                    action: t('Open My projects'),
                },
                {
                    id: 'inviting',
                    title: t('Inviting a freelancer'),
                    body: [
                        t(
                            'The freelancer directory lists published profiles. On a profile, choose Invite and pick one of your open projects.',
                        ),
                        t(
                            'An invitation asks the freelancer to apply; it is not a job offer. It counts as accepted when they submit a proposal. If they decline, you can send it again after seven days.',
                        ),
                    ],
                    href: '/freelancers',
                    action: t('Find freelancers'),
                },
                {
                    id: 'offers',
                    title: t('Conversations and final offers'),
                    body: [
                        t(
                            'Once a freelancer has applied, you can start a private conversation with them from their proposal. A freelancer cannot message a client first.',
                        ),
                        t(
                            'When you agree, send a final offer: the scope, the named deliverables, the price, the delivery time in calendar days and the number of revision rounds included. A project has one pending offer at a time, and an offer expires after 72 hours.',
                        ),
                        t(
                            'The freelancer accepts, declines or asks for changes. Acceptance creates the contract and closes hiring on the project.',
                        ),
                    ],
                    href: '/messages',
                    action: t('Open Messages'),
                },
            ],
        },
        {
            id: 'working',
            title: t('Working'),
            intro: t(
                'Your profile, applying, delivering and getting reviewed.',
            ),
            articles: [
                {
                    id: 'profile',
                    title: t('Publishing your freelancer profile'),
                    body: [
                        t(
                            'A profile needs a headline, a short introduction and at least one skill from the list. A photo, your location and links to your work are optional.',
                        ),
                        t(
                            'A profile is private until you publish it. You need a published profile to apply, and you can unpublish it at any time.',
                        ),
                    ],
                    href: '/my-profile',
                    action: t('Open My profile'),
                },
                {
                    id: 'applying',
                    title: t('Applying to a project'),
                    body: [
                        t(
                            'Open a project in Find work and choose Apply. A proposal has your price, the delivery time in days, a cover message of at least 20 characters, your answers to the client’s questions and up to three links to your work.',
                        ),
                        t(
                            'You can also choose up to three case studies published in your portfolio. The client receives each one as it is when you submit and keeps that copy; work done for a client on Elancer stays shown only while that client’s approval stands.',
                        ),
                        t(
                            'You can attach up to three files of 5 MB each: JPG, PNG, WebP, PDF, TXT or ZIP. Only the project’s client can download them, and only the files of a version you submitted.',
                        ),
                        t(
                            'A draft is private and saves by itself. After you submit, you can still change the proposal or withdraw it while the project is hiring.',
                        ),
                        t(
                            'Applications close on the date shown on the project. The client may then start a conversation with you or send you a final offer.',
                        ),
                    ],
                    href: '/jobs',
                    action: t('Find work'),
                },
                {
                    id: 'sample-projects',
                    title: t('Sample projects from the demo'),
                    body: [
                        t(
                            'Some projects and freelancer profiles are samples: they belong to the demo accounts that visitors explore from the login page, not to real clients.',
                        ),
                        t(
                            'You can apply to a sample project to see how applying works. The page tells you first, and your proposal is marked “Sample project” under My proposals. No real client reads or answers it, and it never becomes a contract.',
                        ),
                    ],
                    href: '/my-proposals',
                    action: t('Open My proposals'),
                },
                {
                    id: 'delivering',
                    title: t('Delivering work and revisions'),
                    body: [
                        t(
                            'Work starts when the client has funded the contract. Talk about progress in the contract’s Messages tab; when the work is ready, submit a delivery from the contract page with a note and any files.',
                        ),
                        t(
                            'A message can carry up to three files of 5 MB each. Only the two of you can download them, and a file cannot be removed once sent.',
                        ),
                        t(
                            'The client approves the delivery or asks for a revision. One revision request lists all the changes and uses one of the rounds included in the agreement.',
                        ),
                        t(
                            'More rounds or more time need both sides to agree to a change of the agreement. The price of a contract does not go up.',
                        ),
                    ],
                    href: '/contracts',
                    action: t('Open Contracts'),
                },
                {
                    id: 'reviews',
                    title: t('Reviews after a contract'),
                    body: [
                        t(
                            'When a contract is completed, the client and the freelancer each give a rating from 1 to 5 and a written review.',
                        ),
                        t(
                            'Reviews stay hidden until both are in or 14 days have passed, so neither side can answer the other. A published review cannot be changed.',
                        ),
                    ],
                    href: '/contracts',
                    action: t('Open Contracts'),
                },
                {
                    id: 'portfolio',
                    title: t('Portfolio and case studies'),
                    body: [
                        t(
                            'Add case studies to your profile with a description, images and links to work you are allowed to show.',
                        ),
                        t(
                            'To show work from a completed contract, ask the client to approve the exact text, images and links. If you change them the approval is asked again, and the client can withdraw it.',
                        ),
                    ],
                    href: '/my-portfolio',
                    action: t('Open My portfolio'),
                },
                {
                    id: 'saving',
                    title: t('Saved projects, freelancers and searches'),
                    body: [
                        t(
                            'Use Save on a project or a freelancer profile to keep it in your Saved list. Nobody else can see what you saved.',
                        ),
                        t(
                            'In Find work you can also save a search with its filters and run it again later. Saved searches do not send email.',
                        ),
                    ],
                    href: '/saved',
                    action: t('Open Saved'),
                },
            ],
        },
        {
            id: 'accounts',
            title: t('Your account'),
            intro: t('Signing in, security, language and settings.'),
            articles: [
                {
                    id: 'signing-in',
                    title: t('Creating an account and signing in'),
                    body: [
                        t(
                            'Register with your email address and a password, or continue with Google or GitHub. Confirm your email address from the message we send before you post or apply.',
                        ),
                        t(
                            'A short setup asks for your name, where you are and how you plan to use Elancer. If you forget your password, use the reset link on the login page.',
                        ),
                    ],
                    href: '/register',
                    action: t('Create an account'),
                },
                {
                    id: 'demo',
                    title: t('Looking around without an account'),
                    body: [
                        t(
                            'On the login and register pages, “Explore as a client” and “Explore as a freelancer” open a prepared demo account with sample projects, proposals and contracts.',
                        ),
                        t(
                            'Demo accounts are shared with other visitors, so some things are switched off there, such as files, the profile and account settings. Anything you change stays inside the demo and is reset from time to time.',
                        ),
                    ],
                    href: '/login',
                    action: t('Open the login page'),
                },
                {
                    id: 'workspaces',
                    title: t('Client and freelancer workspaces'),
                    body: [
                        t(
                            'One account works both ways. The Client and Freelancer switch in the sidebar changes which menus you see: projects and applicants as a client, proposals and invitations as a freelancer.',
                        ),
                        t(
                            'Switching does not change what your account may do, and your messages, contracts and notifications stay the same.',
                        ),
                    ],
                    href: '/dashboard',
                    action: t('Open your workspace'),
                },
                {
                    id: 'security',
                    title: t('Passwords, two-factor and passkeys'),
                    body: [
                        t(
                            'Settings, Security is where you change your password, turn on two-factor authentication with an authenticator app and add passkeys. You can also connect or disconnect Google and GitHub sign-in there.',
                        ),
                        t(
                            'Keep your recovery codes somewhere safe: they let you in if you lose your phone.',
                        ),
                    ],
                    href: '/settings/security',
                    action: t('Open Security settings'),
                },
                {
                    id: 'language',
                    title: t('Language and appearance'),
                    body: [
                        t(
                            'Elancer works in English and Arabic. Use the language button at the top of any page; your choice is saved to your account.',
                        ),
                        t(
                            'Text written by members, such as briefs, proposals and messages, stays in the language it was written in. Light and dark appearance is under Settings, Appearance.',
                        ),
                    ],
                    href: '/settings/appearance',
                    action: t('Open Appearance settings'),
                },
                {
                    id: 'notifications',
                    title: t('Notifications and email'),
                    body: [
                        t(
                            'The bell shows every update about your projects, proposals, offers, contracts and messages.',
                        ),
                        t(
                            'Under Settings, Notifications you choose which of them are also sent by email. Emails about offers and contracts are on unless you switch them off; security emails are always sent.',
                        ),
                        t(
                            'An email about messages waits 15 minutes and goes only if the conversation is still unread then, at most once an hour for each conversation. It says how many messages are waiting, never what they say.',
                        ),
                    ],
                    href: '/settings/notifications',
                    action: t('Open Notification settings'),
                },
                {
                    id: 'api-tokens',
                    title: t('API tokens'),
                    body: [
                        t(
                            'A token lets a program you run read public projects and manage your own proposals. Create one under Settings, API tokens: give it a name, choose what it may do and when it expires, from 7 to 90 days.',
                        ),
                        t(
                            'The token is shown once, so copy it then. Revoke a token as soon as you no longer need it or think someone else has seen it.',
                        ),
                    ],
                    href: '/settings/tokens',
                    action: t('Open API tokens'),
                },
                {
                    id: 'ai-agents',
                    title: t('Connect an AI agent'),
                    body: [
                        t(
                            'An AI agent or app that supports MCP can search projects, prepare and send your proposals, and read your projects and the proposals they received. Its address is on Settings, API tokens.',
                        ),
                        t(
                            'An app with a Connect button, such as Claude, ChatGPT, Cursor or VS Code, opens an Elancer page that names the app and where it returns to. Approve only an app you started yourself. A connected app may use every tool, and you can disconnect it on the same settings page.',
                        ),
                        t(
                            'An agent that takes a header instead, such as Claude Code, uses an API token: it can do only what you gave the token. Ask your agent to check with you before it submits or withdraws a proposal.',
                        ),
                        t(
                            'Elancer’s free hosting sleeps when nobody uses it, so the first call after a quiet time can take up to a minute.',
                        ),
                    ],
                    href: '/settings/tokens',
                    action: t('Open API tokens'),
                },
                {
                    id: 'closing-account',
                    title: t('Closing your account'),
                    body: [
                        t(
                            'You can delete your account from Settings, Profile. Your profile, drafts and saved items are removed with it.',
                        ),
                        t(
                            'An account that has a contract cannot be closed, because the other side still needs the record.',
                        ),
                    ],
                    href: '/settings/profile',
                    action: t('Open Profile settings'),
                },
            ],
        },
        {
            id: 'payments',
            title: t('Payments'),
            intro: t('Funding a contract, test mode and cancellations.'),
            articles: [
                {
                    id: 'test-mode',
                    title: t('Payments are in test mode'),
                    body: [
                        t(
                            'Elancer is a portfolio project. Payments run through a test provider: no real money moves, no fee is charged and nothing is paid out.',
                        ),
                        t(
                            'The amounts you see are the ones agreed in your contracts. There is no balance, wallet or withdrawal.',
                        ),
                        t(
                            'Never type a real card on the payment page. Use the provider’s test card: on Stripe 4242 4242 4242 4242, on Moyasar 4111 1111 1111 1111, with any future date and any three-digit code.',
                        ),
                    ],
                    href: '/finance',
                    action: t('Open Finance'),
                },
                {
                    id: 'funding',
                    title: t('Funding a contract'),
                    body: [
                        t(
                            'After a freelancer accepts your final offer, the contract waits for payment. Open the contract and choose a payment option to fund the agreed amount.',
                        ),
                        t(
                            'Work, deliveries and revisions open once the payment is confirmed. If a payment is not completed, you can start it again from the same page.',
                        ),
                    ],
                    href: '/contracts',
                    action: t('Open Contracts'),
                },
                {
                    id: 'cancelling',
                    title: t('Cancelling a contract'),
                    body: [
                        t(
                            'Either side can ask to cancel a contract, and it is cancelled only when the other side agrees. While a request is open, messages still work and deliveries are paused.',
                        ),
                        t(
                            'If the contract was funded, the cancellation completes when the test payment has been refunded. Elancer staff do not decide who is right and do not force a completion or a refund.',
                        ),
                    ],
                    href: '/contracts',
                    action: t('Open Contracts'),
                },
                {
                    id: 'finance',
                    title: t('The Finance page'),
                    body: [
                        t(
                            'Finance lists your contracts with their agreed amounts and whether each is funded, separately for your client side and your freelancer side.',
                        ),
                        t(
                            'Payment history shows every payment attempt with its date, provider and result.',
                        ),
                    ],
                    href: '/finance',
                    action: t('Open Finance'),
                },
            ],
        },
        {
            id: 'safety',
            title: t('Safety'),
            intro: t(
                'Reporting, blocking and keeping your account in good standing.',
            ),
            articles: [
                {
                    id: 'reporting',
                    title: t('Reporting a problem'),
                    body: [
                        t(
                            'Use Report on a project, a freelancer profile, a message or a contract when something breaks the rules. Choose a reason and describe what happened.',
                        ),
                        t(
                            'Follow your reports under My reports: Submitted, In review, then Resolved with a short outcome. Reviewers may read the reported conversation to decide. There is no separate support ticket system.',
                        ),
                    ],
                    href: '/my-reports',
                    action: t('Open My reports'),
                },
                {
                    id: 'blocking',
                    title: t('Blocking an account'),
                    body: [
                        t(
                            'Block someone from their profile, an invitation or a conversation. They can no longer invite you, apply to your projects or start new hiring contact with you.',
                        ),
                        t(
                            'A contract you already share keeps working, including its messages. Manage your list under Blocked accounts.',
                        ),
                    ],
                    href: '/blocked-accounts',
                    action: t('Open Blocked accounts'),
                },
                {
                    id: 'suspension',
                    title: t('If your account is suspended'),
                    body: [
                        t(
                            'A suspended account cannot post, apply, send offers or fund contracts. You can still sign in and handle the contracts you already have.',
                        ),
                        t(
                            'A banner in your workspace gives the reason. An administrator can reinstate the account.',
                        ),
                    ],
                    href: '/dashboard',
                    action: t('Open your workspace'),
                },
                {
                    id: 'identity',
                    title: t('Identity verification'),
                    body: [
                        t(
                            'Verification is optional and is not needed to post or apply. If you choose it, you upload one photo of an identity document and one selfie, JPEG or PNG, up to 2 MB each, and agree to the review.',
                        ),
                        t(
                            'Only authorised reviewers see the images. They are stored encrypted and deleted after the review.',
                        ),
                    ],
                    href: '/my-profile',
                    action: t('Open My profile'),
                },
            ],
        },
    ];
}

/**
 * Folds what should not matter in a search: letter case, Arabic marks and the
 * stretching dash, the written forms of alef, final yeh and teh marbuta.
 */
export function foldForSearch(text: string): string {
    return text
        .toLocaleLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ًͯ-ٰٟـ]/g, '')
        .replace(/[آأإٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/[“”"’'.,:;!?()[\]{}،؛؟]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}
