import AnimateIn from './AnimateIn';
import { ArrowRight, MaskIcon } from './Icons';

const metrics = [
    { icon: '/assets/metbati/icons/27-location.png', title: 'Algiers', sub: 'Live Today' },
    { icon: '/assets/metbati/icons/23-office.png', title: '2030', sub: 'Expansion Target' },
    { icon: '/assets/metbati/icons/25-metro.png', title: '1,000+', sub: 'Kiosk Vision' },
];

export default function Vision() {
    return (
        <section id="partner" className="section-padding section-y bg-gray-50 border-t border-gray-200">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <AnimateIn direction="left">
                    <h2 className="heading-section mb-4">Starting in Algiers. Built for Algeria.</h2>
                    <p className="text-body mb-10 max-w-md">
                        Metbati kiosks are live in Algiers today, with a platform built to scale to 1,000+ kiosks
                        across Algeria by 2030.
                    </p>
                    <div className="grid grid-cols-3 gap-6 mb-10 max-w-md">
                        {metrics.map((m) => (
                            <div key={m.sub}>
                                <MaskIcon src={m.icon} size={28} className="mb-3" />
                                <p className="font-extrabold text-h3 text-ink">{m.title}</p>
                                <p className="text-small text-slate mt-1">{m.sub}</p>
                            </div>
                        ))}
                    </div>
                    <a className="btn-secondary" href="#contact">
                        View All Locations <ArrowRight />
                    </a>
                </AnimateIn>
                <AnimateIn direction="right" className="flex flex-col items-center">
                    <img
                        alt="Illustration of Metbati's 2030 vision for a nationwide kiosk network"
                        src="/images/algeria-vision.png"
                        width={720}
                        height={640}
                        className="w-full max-w-lg h-auto drop-shadow-xl recolor-green"
                        onError={(e) => {
                            e.currentTarget.src = '/images/vision-fallback.svg';
                        }}
                    />
                    <p className="text-caption uppercase tracking-widest text-slate mt-3">
                        Our 2030 vision (not current coverage)
                    </p>
                </AnimateIn>
            </div>
        </section>
    );
}
