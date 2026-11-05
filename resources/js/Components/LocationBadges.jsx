import AnimateIn from './AnimateIn';
import { MaskIcon } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const officeIcon = '/assets/metbati/icons/23-office.png';

export default function LocationBadges() {
    const { t } = useLandingLocale();

    return (
        <section className="section-padding py-14 md:py-16 bg-white border-b border-gray-200">
            <AnimateIn>
                <p className="text-center text-base md:text-lg font-medium uppercase tracking-widest text-slate mb-8">
                    {t.locations.eyebrow}
                </p>
                <div className="flex flex-nowrap md:flex-wrap items-center justify-start md:justify-center gap-x-10 gap-y-6 overflow-x-auto pb-2 md:pb-0 snap-x snap-mandatory">
                    {t.locations.items.map((name) => (
                        <div
                            key={name}
                            className="flex items-center gap-2 text-gray-400 grayscale opacity-70 transition-all duration-300 hover:text-primary hover:grayscale-0 hover:opacity-100 cursor-default shrink-0 snap-center"
                        >
                            <MaskIcon src={officeIcon} size={28} />
                            <span className="font-bold text-xl md:text-2xl tracking-tight">{name}</span>
                        </div>
                    ))}
                </div>
            </AnimateIn>
        </section>
    );
}
