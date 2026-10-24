export default function DuplexIcon({ className = 'h-5 w-5', active = false }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden
        >
            {/* Front sheet */}
            <rect
                x="3.5"
                y="4"
                width="10"
                height="14"
                rx="1.5"
                stroke="currentColor"
                strokeWidth="1.7"
                fill={active ? 'currentColor' : 'none'}
                fillOpacity={active ? 0.12 : 0}
            />
            <path
                d="M6 8h5M6 11h5M6 14h3.5"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                opacity={active ? 0.9 : 0.55}
            />
            {/* Back sheet */}
            <rect
                x="10.5"
                y="6"
                width="10"
                height="14"
                rx="1.5"
                stroke="currentColor"
                strokeWidth="1.7"
                fill={active ? 'currentColor' : 'none'}
                fillOpacity={active ? 0.08 : 0}
            />
            <path
                d="M13.2 10h4.5M13.2 13h4.5M13.2 16h3"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                opacity={active ? 0.85 : 0.45}
            />
            {/* Flip cue */}
            <path
                d="M9.2 19.2c1.6 1.1 3.8 1.1 5.4 0"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                opacity={active ? 1 : 0.5}
            />
        </svg>
    );
}
