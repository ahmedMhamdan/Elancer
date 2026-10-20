// Q33 project covers: Elancer's own inline drawings, one per kind of work. Colours come from the
// theme tokens through the --art-* variables in elancer-discovery.css, so light and dark follow.
import { useId } from 'react';

export type WorkKind =
    | 'development'
    | 'design'
    | 'writing'
    | 'marketing'
    | 'video'
    | 'data'
    | 'business'
    | 'engineering'
    | 'general';
export type ArtTone = 'soft' | 'ink' | 'paper';
export const artTones: ArtTone[] = ['soft', 'ink', 'paper'];

// Category names are free text in English or Arabic, so the kind is read from its words.
// The first match wins: "Software engineering" is development, "Video editing" is video.
const kinds: [WorkKind, RegExp][] = [
    [
        'development',
        /develop|software|programm|coding|\bweb|\bapps?\b|mobile|\bit\b|تطوير|برمج|تطبيق|مواقع|تقنية|جوال/,
    ],
    ['data', /\bdata|analy|statistic|machine learning|بيانات|تحليل|إحصاء/],
    [
        'design',
        /design|creative|brand|graphic|illustrat|\bu[ix]\b|تصميم|إبداع|هوية|جرافيك/,
    ],
    [
        'video',
        /video|animat|motion|film|photo|فيديو|تحريك|مونتاج|تصوير|رسوم متحركة/,
    ],
    [
        'writing',
        /writ|translat|content|copy|editing|كتابة|ترجمة|محتوى|تحرير|تدقيق/,
    ],
    ['marketing', /market|sales|\bseo\b|social|advert|تسويق|مبيعات|إعلان/],
    ['engineering', /engineer|architect|\bcad\b|construction|هندس|معمار|إنشاء/],
    [
        'business',
        /business|consult|financ|account|legal|manage|admin|support|أعمال|استشار|إدار|محاسب|مالي|قانون/,
    ],
];
export function workKind(category: string): WorkKind {
    const name = category.toLowerCase();
    return kinds.find(([, words]) => words.test(name))?.[0] ?? 'general';
}

function Development() {
    return (
        <>
            <circle className="art-soft" cx="338" cy="36" r="92" />
            <rect
                className="art-shadow"
                x="50"
                y="50"
                width="236"
                height="160"
                rx="14"
            />
            <rect
                className="art-paper"
                x="44"
                y="42"
                width="236"
                height="160"
                rx="14"
            />
            <path
                className="art-tint"
                d="M44 56a14 14 0 0 1 14-14h208a14 14 0 0 1 14 14v14H44z"
            />
            <circle className="art-warm" cx="64" cy="56" r="4.5" />
            <circle className="art-pop" cx="80" cy="56" r="4.5" />
            <circle className="art-faint" cx="96" cy="56" r="4.5" />
            <rect
                className="art-pop"
                x="64"
                y="88"
                width="44"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-faint"
                x="116"
                y="88"
                width="92"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-faint"
                x="82"
                y="107"
                width="58"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-warm"
                x="148"
                y="107"
                width="40"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-faint"
                x="82"
                y="126"
                width="118"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-ink"
                x="82"
                y="145"
                width="34"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-pop"
                x="124"
                y="145"
                width="62"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-faint"
                x="64"
                y="164"
                width="36"
                height="9"
                rx="4.5"
            />
            <rect
                className="art-ink"
                x="252"
                y="80"
                width="96"
                height="140"
                rx="16"
            />
            <rect
                className="art-paper"
                x="259"
                y="88"
                width="82"
                height="124"
                rx="10"
            />
            <rect
                className="art-pop"
                x="269"
                y="98"
                width="62"
                height="40"
                rx="7"
            />
            <rect
                className="art-faint"
                x="269"
                y="148"
                width="62"
                height="7"
                rx="3.5"
            />
            <rect
                className="art-faint"
                x="269"
                y="162"
                width="40"
                height="7"
                rx="3.5"
            />
            <rect
                className="art-ink"
                x="269"
                y="182"
                width="62"
                height="18"
                rx="9"
            />
        </>
    );
}

function Design() {
    return (
        <>
            <circle className="art-soft" cx="60" cy="204" r="84" />
            <rect
                className="art-shadow"
                x="62"
                y="46"
                width="214"
                height="160"
                rx="14"
            />
            <rect
                className="art-paper"
                x="56"
                y="38"
                width="214"
                height="160"
                rx="14"
            />
            <circle className="art-pop" cx="128" cy="126" r="42" />
            <rect
                className="art-ink"
                x="152"
                y="94"
                width="70"
                height="70"
                rx="12"
                transform="rotate(10 187 129)"
            />
            <path className="art-warm" d="M90 180l32-54 32 54z" />
            <path className="art-line" d="M84 76C120 44 200 100 244 62" />
            <path className="art-line-thin" d="M84 76l28-20M244 62l-30 14" />
            <circle className="art-ink" cx="112" cy="56" r="4" />
            <circle className="art-ink" cx="214" cy="76" r="4" />
            <rect
                className="art-node"
                x="79"
                y="71"
                width="10"
                height="10"
                rx="2"
            />
            <rect
                className="art-node"
                x="239"
                y="57"
                width="10"
                height="10"
                rx="2"
            />
            <rect
                className="art-ink"
                x="294"
                y="46"
                width="58"
                height="58"
                rx="14"
            />
            <rect
                className="art-pop"
                x="294"
                y="114"
                width="58"
                height="36"
                rx="12"
            />
            <rect
                className="art-warm"
                x="294"
                y="160"
                width="58"
                height="36"
                rx="12"
            />
            <path className="art-cursor" d="M226 152l10 34 8-13 14-4z" />
        </>
    );
}

function Writing() {
    return (
        <>
            <circle className="art-soft" cx="330" cy="210" r="88" />
            <rect
                className="art-shadow"
                x="70"
                y="38"
                width="176"
                height="186"
                rx="14"
            />
            <rect
                className="art-paper"
                x="64"
                y="30"
                width="176"
                height="186"
                rx="14"
            />
            <rect
                className="art-ink"
                x="86"
                y="54"
                width="84"
                height="12"
                rx="6"
            />
            <rect
                className="art-faint"
                x="86"
                y="82"
                width="132"
                height="8"
                rx="4"
            />
            <rect
                className="art-faint"
                x="86"
                y="98"
                width="118"
                height="8"
                rx="4"
            />
            <rect
                className="art-faint"
                x="86"
                y="114"
                width="132"
                height="8"
                rx="4"
            />
            <rect
                className="art-faint"
                x="86"
                y="130"
                width="70"
                height="8"
                rx="4"
            />
            <rect
                className="art-tint"
                x="86"
                y="152"
                width="132"
                height="44"
                rx="10"
            />
            <rect
                className="art-pop"
                x="86"
                y="152"
                width="6"
                height="44"
                rx="3"
            />
            <rect
                className="art-faint"
                x="104"
                y="166"
                width="96"
                height="7"
                rx="3.5"
            />
            <rect
                className="art-faint"
                x="104"
                y="180"
                width="62"
                height="7"
                rx="3.5"
            />
            <rect
                className="art-ink"
                x="262"
                y="44"
                width="72"
                height="72"
                rx="18"
            />
            <text
                className="art-on-ink"
                x="298"
                y="94"
                textAnchor="middle"
                fontSize="40"
                fontWeight="600"
            >
                A
            </text>
            <rect
                className="art-pop art-edge"
                x="294"
                y="104"
                width="72"
                height="72"
                rx="18"
            />
            <text
                className="art-on-pop"
                x="330"
                y="152"
                textAnchor="middle"
                fontSize="40"
                fontWeight="600"
            >
                ع
            </text>
        </>
    );
}

// Plan 032 part 9: one megaphone (mouthpiece, body, bell and its rim, the grip under the body leaning
// back toward the speaker) facing its sound waves.
function Marketing() {
    return (
        <>
            <circle className="art-soft" cx="70" cy="40" r="88" />
            <path
                className="art-warm"
                d="M88 140h22l-8 46a8 8 0 0 1-8 7h-8a8 8 0 0 1-8-9z"
            />
            <rect
                className="art-ink"
                x="58"
                y="110"
                width="20"
                height="26"
                rx="6"
            />
            <rect
                className="art-pop"
                x="72"
                y="102"
                width="50"
                height="42"
                rx="10"
            />
            <path
                className="art-ink"
                d="M116 108l78-44a8 8 0 0 1 12 7v104a8 8 0 0 1-12 7l-78-44z"
            />
            <rect
                className="art-pop"
                x="200"
                y="62"
                width="14"
                height="122"
                rx="7"
            />
            <path className="art-line-pop" d="M228 104a26 26 0 0 1 0 38" />
            <path className="art-line-pop" d="M246 86a56 56 0 0 1 0 74" />
            <rect
                className="art-shadow"
                x="284"
                y="130"
                width="92"
                height="84"
                rx="14"
            />
            <rect
                className="art-paper"
                x="278"
                y="122"
                width="92"
                height="84"
                rx="14"
            />
            <rect
                className="art-faint"
                x="294"
                y="170"
                width="11"
                height="20"
                rx="3"
            />
            <rect
                className="art-faint"
                x="311"
                y="160"
                width="11"
                height="30"
                rx="3"
            />
            <rect
                className="art-faint"
                x="328"
                y="148"
                width="11"
                height="42"
                rx="3"
            />
            <rect
                className="art-pop"
                x="345"
                y="136"
                width="11"
                height="54"
                rx="3"
            />
            <circle className="art-paper" cx="322" cy="62" r="30" />
            <circle className="art-line-warm" cx="322" cy="62" r="19" />
            <circle className="art-warm" cx="322" cy="62" r="8" />
        </>
    );
}

function Video({ clip }: { clip: string }) {
    return (
        <>
            <circle className="art-soft" cx="56" cy="214" r="80" />
            <clipPath id={clip}>
                <rect x="56" y="30" width="288" height="146" rx="16" />
            </clipPath>
            <rect
                className="art-ink"
                x="56"
                y="30"
                width="288"
                height="146"
                rx="16"
            />
            <g clipPath={`url(#${clip})`}>
                <circle className="art-warm" cx="284" cy="72" r="22" />
                <path
                    className="art-pop"
                    d="M56 176v-34c44-38 92-36 138-4s96 26 150-20v58z"
                />
                <path
                    className="art-on-ink art-half"
                    d="M56 176v-10c50-22 110-20 160-2s90 8 128-12v24z"
                />
            </g>
            <circle className="art-paper" cx="200" cy="98" r="30" />
            <path className="art-ink" d="M191 83l26 15-26 15z" />
            <rect
                className="art-paper"
                x="56"
                y="188"
                width="288"
                height="34"
                rx="12"
            />
            <rect
                className="art-pop"
                x="68"
                y="197"
                width="76"
                height="16"
                rx="6"
            />
            <rect
                className="art-faint"
                x="150"
                y="197"
                width="48"
                height="16"
                rx="6"
            />
            <rect
                className="art-warm"
                x="204"
                y="197"
                width="64"
                height="16"
                rx="6"
            />
            <rect
                className="art-faint"
                x="274"
                y="197"
                width="58"
                height="16"
                rx="6"
            />
            <rect
                className="art-ink"
                x="184"
                y="184"
                width="4"
                height="42"
                rx="2"
            />
            <circle className="art-ink" cx="186" cy="184" r="6" />
        </>
    );
}

function Data() {
    return (
        <>
            <circle className="art-soft" cx="344" cy="214" r="86" />
            <rect
                className="art-shadow"
                x="50"
                y="44"
                width="216"
                height="168"
                rx="14"
            />
            <rect
                className="art-paper"
                x="44"
                y="36"
                width="216"
                height="168"
                rx="14"
            />
            <rect
                className="art-ink"
                x="64"
                y="54"
                width="60"
                height="10"
                rx="5"
            />
            <path className="art-grid" d="M64 86h176M64 122h176M64 158h176" />
            <rect
                className="art-faint"
                x="72"
                y="138"
                width="22"
                height="44"
                rx="5"
            />
            <rect
                className="art-faint"
                x="106"
                y="112"
                width="22"
                height="70"
                rx="5"
            />
            <rect
                className="art-faint"
                x="140"
                y="126"
                width="22"
                height="56"
                rx="5"
            />
            <rect
                className="art-pop"
                x="174"
                y="84"
                width="22"
                height="98"
                rx="5"
            />
            <rect
                className="art-faint"
                x="208"
                y="102"
                width="22"
                height="80"
                rx="5"
            />
            <path className="art-line" d="M83 122l34-26 34 14 34-44 34 18" />
            {[
                [83, 122],
                [117, 96],
                [151, 110],
                [185, 66],
                [219, 84],
            ].map(([x, y]) => (
                <circle key={x} className="art-node" cx={x} cy={y} r="5" />
            ))}
            <circle className="art-ring" cx="320" cy="92" r="36" />
            <circle
                className="art-ring-pop"
                cx="320"
                cy="92"
                r="36"
                strokeDasharray="140 227"
                transform="rotate(-90 320 92)"
            />
            <rect
                className="art-ink"
                x="282"
                y="150"
                width="78"
                height="46"
                rx="14"
            />
            <path
                className="art-line-pop art-fine"
                d="M298 181l13-13 10 8 18-18M329 158h10v10"
            />
        </>
    );
}

function Business() {
    return (
        <>
            <circle className="art-soft" cx="336" cy="44" r="90" />
            <rect
                className="art-shadow"
                x="56"
                y="48"
                width="160"
                height="164"
                rx="14"
            />
            <rect
                className="art-paper"
                x="50"
                y="40"
                width="160"
                height="164"
                rx="14"
            />
            <rect
                className="art-ink"
                x="70"
                y="60"
                width="72"
                height="11"
                rx="5.5"
            />
            {[88, 124, 160].map((y, row) => (
                <g key={y}>
                    <rect
                        className={row < 2 ? 'art-pop' : 'art-tint'}
                        x="70"
                        y={y}
                        width="24"
                        height="24"
                        rx="7"
                    />
                    {row < 2 && (
                        <path
                            className="art-check"
                            d={`M77 ${y + 12}l4 4 8-9`}
                        />
                    )}
                    <rect
                        className="art-faint"
                        x="106"
                        y={y + 4}
                        width="84"
                        height="7"
                        rx="3.5"
                    />
                    <rect
                        className="art-faint"
                        x="106"
                        y={y + 15}
                        width="52"
                        height="7"
                        rx="3.5"
                    />
                </g>
            ))}
            <path
                className="art-line-dash"
                d="M240 190c30 0 26-58 56-58s26-56 52-56"
            />
            <circle className="art-ink" cx="240" cy="190" r="11" />
            <circle className="art-pop" cx="296" cy="132" r="13" />
            <circle className="art-warm" cx="348" cy="76" r="17" />
            <path className="art-flag" d="M343 85V67l12 5-12 5" />
        </>
    );
}

function Engineering() {
    return (
        <>
            <rect
                className="art-shadow"
                x="50"
                y="44"
                width="312"
                height="172"
                rx="14"
            />
            <rect
                className="art-paper"
                x="44"
                y="36"
                width="312"
                height="172"
                rx="14"
            />
            <path
                className="art-grid"
                d="M70 36v172M96 36v172M122 36v172M148 36v172M174 36v172M200 36v172M226 36v172M252 36v172M278 36v172M304 36v172M330 36v172M44 62h312M44 88h312M44 114h312M44 140h312M44 166h312M44 192h312"
            />
            <path className="art-line" d="M72 170h256" />
            <path className="art-line-pop" d="M100 170a100 82 0 0 1 200 0" />
            <path
                className="art-line-thin"
                d="M140 104.4V170M170 91.8V170M200 88V170M230 91.8V170M260 104.4V170"
            />
            <rect
                className="art-ink"
                x="92"
                y="170"
                width="16"
                height="22"
                rx="3"
            />
            <rect
                className="art-ink"
                x="292"
                y="170"
                width="16"
                height="22"
                rx="3"
            />
            <path
                className="art-line-thin"
                d="M100 62h200M100 55v14M300 55v14"
            />
            <rect
                className="art-warm"
                x="178"
                y="52"
                width="44"
                height="20"
                rx="10"
            />
            <circle
                className="art-ring-warm"
                cx="326"
                cy="110"
                r="17"
                strokeDasharray="7 6.35"
            />
            <circle className="art-warm" cx="326" cy="110" r="13" />
            <circle className="art-paper" cx="326" cy="110" r="5" />
        </>
    );
}

function General() {
    return (
        <>
            <circle className="art-soft" cx="330" cy="50" r="96" />
            <circle className="art-pop" cx="150" cy="124" r="66" />
            <rect
                className="art-ink"
                x="178"
                y="62"
                width="124"
                height="124"
                rx="28"
            />
            <circle className="art-warm" cx="298" cy="178" r="32" />
            <path
                className="art-on-ink"
                d="M240 92c4 20 12 28 32 32-20 4-28 12-32 32-4-20-12-28-32-32 20-4 28-12 32-32z"
            />
            <rect
                className="art-ink"
                x="74"
                y="46"
                width="18"
                height="18"
                rx="3"
            />
            {[64, 80, 96].map((x) =>
                [190, 206].map((y) => (
                    <circle
                        key={`${x}-${y}`}
                        className="art-faint"
                        cx={x}
                        cy={y}
                        r="3"
                    />
                )),
            )}
        </>
    );
}

export default function ProjectArt({
    kind,
    tone,
}: {
    kind: WorkKind;
    tone: ArtTone;
}) {
    const clip = useId();
    return (
        <svg
            className="home-art"
            data-tone={tone}
            data-kind={kind}
            viewBox="0 0 400 240"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
            focusable="false"
        >
            <rect className="art-bg" width="400" height="240" />
            {kind === 'development' && <Development />}
            {kind === 'design' && <Design />}
            {kind === 'writing' && <Writing />}
            {kind === 'marketing' && <Marketing />}
            {kind === 'video' && <Video clip={clip} />}
            {kind === 'data' && <Data />}
            {kind === 'business' && <Business />}
            {kind === 'engineering' && <Engineering />}
            {kind === 'general' && <General />}
        </svg>
    );
}

// A drawn initial for a member without a photo; the tint is fixed per profile so it never flickers.
export function Monogram({ name, seed }: { name: string; seed: number }) {
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('');
    return (
        <svg
            className="home-monogram"
            data-tone={artTones[seed % artTones.length]}
            viewBox="0 0 64 64"
            aria-hidden="true"
            focusable="false"
        >
            <rect className="art-bg" width="64" height="64" />
            <circle
                className="art-pop art-half"
                cx={seed % 2 ? 58 : 6}
                cy="62"
                r="20"
            />
            <text
                className="art-ink"
                x="32"
                y="33"
                textAnchor="middle"
                dominantBaseline="middle"
                fontSize="22"
                fontWeight="600"
            >
                {initials}
            </text>
        </svg>
    );
}
