import AnimateIn from './AnimateIn';
import { ArrowRight, MaskIcon } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const metricIcons = [
    '/assets/metbati/icons/27-location.png',
    '/assets/metbati/icons/23-office.png',
    '/assets/metbati/icons/25-metro.png',
];

export default function Vision() {
    const { t, isAr } = useLandingLocale();

    return (
        <section id="partner" className="section-padding section-y bg-gray-50 border-t border-gray-200">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <AnimateIn direction="left">
                    <h2 className="heading-section mb-4">{t.vision.title}</h2>
                    <p className="text-body mb-10 max-w-md">{t.vision.body}</p>
                    <div className="grid grid-cols-3 gap-6 mb-10 max-w-md">
                        {t.vision.metrics.map((m, i) => (
                            <div key={m.sub}>
                                <MaskIcon src={metricIcons[i]} size={28} className="mb-3" />
                                <p className="font-extrabold text-h3 text-ink">{m.title}</p>
                                <p className="text-small text-slate mt-1">{m.sub}</p>
                            </div>
                        ))}
                    </div>
                    <a className="btn-secondary" href="#contact">
                        {t.vision.cta} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                    </a>
                </AnimateIn>
                <AnimateIn direction="right" className="flex flex-col items-center">
                    <img
                        alt={t.vision.alt}
                        src="/images/algeria-vision.png"
                        width={720}
                        height={640}
                        className="w-full max-w-lg h-auto drop-shadow-xl recolor-green"
                        onError={(e) => {
                            e.currentTarget.src = '/images/vision-fallback.svg';
                        }}
                    />
                    <p className="text-caption uppercase tracking-widest text-slate mt-3">{t.vision.caption}</p>
                </AnimateIn>
            </div>
        </section>
    );
}
