const TONE = {
    up: { face: '#059669', wash: '#d1fae5', ink: '#065f46' },
    down: { face: '#e11d48', wash: '#ffe4e6', ink: '#9f1239' },
    quiet: { face: '#d97706', wash: '#fef3c7', ink: '#92400e' },
};

export default function FloorArt({ kind, state }) {
    const tone = TONE[state] || TONE.quiet;

    return (
        <svg viewBox="0 0 88 88" className="h-[72px] w-[72px] shrink-0" aria-hidden="true">
            <rect x="4" y="4" width="80" height="80" rx="22" fill={tone.wash} />
            <Scene kind={kind} tone={tone} />
        </svg>
    );
}

function Scene({ kind, tone }) {
    if (kind === 'database') {
        return (
            <g fill="none" stroke={tone.ink} strokeWidth="2.2" strokeLinecap="round">
                <ellipse cx="44" cy="30" rx="16" ry="6" fill={tone.face} stroke="none" />
                <path d="M28 30v18c0 3.3 7.2 6 16 6s16-2.7 16-6V30" />
                <path d="M28 39c0 3.3 7.2 6 16 6s16-2.7 16-6" />
            </g>
        );
    }

    if (kind === 'storage') {
        return (
            <g fill="none" stroke={tone.ink} strokeWidth="2.2" strokeLinejoin="round">
                <path d="M26 36h36v22H26z" fill={tone.face} stroke="none" />
                <path d="M24 34h14l4 5h22v20H24z" />
                <path d="M32 48h16" />
            </g>
        );
    }

    if (kind === 'reverb') {
        return (
            <g fill="none" stroke={tone.ink} strokeWidth="2.2" strokeLinecap="round">
                <circle cx="44" cy="48" r="4" fill={tone.face} stroke="none" />
                <path d="M36 40a12 12 0 0 1 16 0" />
                <path d="M30 34a20 20 0 0 1 28 0" />
                <path d="M36 56a12 12 0 0 1 16 0" />
                <path d="M30 62a20 20 0 0 1 28 0" />
            </g>
        );
    }

    if (kind === 'socket') {
        return (
            <g fill="none" stroke={tone.ink} strokeWidth="2.2" strokeLinejoin="round">
                <rect x="28" y="28" width="32" height="22" rx="4" fill={tone.face} stroke="none" />
                <rect x="28" y="28" width="32" height="22" rx="4" />
                <path d="M36 50v8h16v-8" />
                <path d="M32 62h24" />
                <path d="M40 36h8" stroke="#fff" />
            </g>
        );
    }

    if (kind === 'coin') {
        return (
            <g fill="none" stroke={tone.ink} strokeWidth="2.2">
                <circle cx="44" cy="44" r="14" fill={tone.face} stroke="none" />
                <circle cx="44" cy="44" r="14" />
                <path d="M44 36v16M39 40h8a4 4 0 0 1 0 8h-6" strokeLinecap="round" />
            </g>
        );
    }

    if (kind === 'printer') {
        return (
            <g fill="none" stroke={tone.ink} strokeWidth="2.2" strokeLinejoin="round">
                <path d="M32 40V30h24v10" />
                <rect x="26" y="40" width="36" height="16" rx="3" fill={tone.face} stroke="none" />
                <rect x="26" y="40" width="36" height="16" rx="3" />
                <path d="M32 56h24v8H32z" />
                <circle cx="56" cy="46" r="1.4" fill={tone.ink} stroke="none" />
            </g>
        );
    }

    return (
        <g fill="none" stroke={tone.ink} strokeWidth="2.2" strokeLinecap="round">
            <circle cx="44" cy="44" r="14" />
            <path d="M44 36v8l5 3" />
            <circle cx="58" cy="32" r="5" fill={tone.face} stroke="none" />
        </g>
    );
}
