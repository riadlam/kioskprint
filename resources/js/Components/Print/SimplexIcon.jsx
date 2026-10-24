export default function SimplexIcon({ className = 'h-5 w-5', active = false }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden>
            <rect
                x="6"
                y="3.5"
                width="12"
                height="17"
                rx="1.6"
                stroke="currentColor"
                strokeWidth="1.7"
                fill={active ? 'currentColor' : 'none'}
                fillOpacity={active ? 0.12 : 0}
            />
            <path
                d="M9 8h6M9 11.5h6M9 15h4"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                opacity={active ? 0.95 : 0.55}
            />
        </svg>
    );
}
