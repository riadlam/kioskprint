export function KioskMark({ size = 32, light = false }) {
    const stroke = light ? '#F8F9FA' : '#0D0F14';
    const fillDark = light ? '#0D0F14' : '#F8F9FA';
    const accent = '#059669';
    const accentShadow = '#047857';

    return (
        <svg
            width={size}
            height={size * (124 / 132)}
            viewBox="0 0 132 124"
            style={{ overflow: 'visible', display: 'block' }}
            aria-hidden="true"
        >
            <rect x="6" y="6" width="120" height="112" rx="26" fill="none" stroke={stroke} strokeWidth="11" />
            <rect x="30" y="32" width="46" height="9" rx="4.5" fill={stroke} />
            <circle cx="96" cy="36" r="8" fill={accent} />
            <rect x="30" y="66" width="72" height="11" rx="5.5" fill={stroke} />
            <path d="M48 77 H84 V138 L73 149 H48 Z" fill={fillDark} stroke={fillDark} strokeWidth="10" />
            <path d="M48 77 H84 V138 L73 149 H48 Z" fill={accent} />
            <path d="M84 138 L73 138 L73 149 Z" fill={accentShadow} />
            <rect x="56" y="94" width="18" height="5" rx="2.5" fill={fillDark} />
            <rect x="56" y="105" width="11" height="5" rx="2.5" fill={fillDark} />
        </svg>
    );
}

export default function Logo({ light = false, className = '' }) {
    return (
        <div className={`flex items-center gap-3 ${className}`}>
            <KioskMark size={32} light={light} />
            <span className="font-extrabold text-xl md:text-2xl tracking-tight leading-none">
                <span className={light ? 'text-[#F8F9FA]' : 'text-ink'}>MET</span>
                <span className="text-primary">BATI</span>
            </span>
        </div>
    );
}
