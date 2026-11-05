import AnimateIn from './AnimateIn';
import { ArrowRight, MaskIcon } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const featureIcons = [
    '/assets/metbati/icons/03-cloud.png',
    '/assets/metbati/icons/10-ai-monitoring.png',
    '/assets/metbati/icons/13-security.png',
    '/assets/metbati/icons/26-franchise.png',
    '/assets/metbati/icons/05-payment.png',
    '/assets/metbati/icons/29-24x7.png',
];

export default function Features() {
    const { t, isAr } = useLandingLocale();

    return (
        <section id="about" className="section-padding section-y bg-[#F8F9FA]">
            <AnimateIn>
                <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8 sm:mb-12">
                    <div className="min-w-0">
                        <p className="eyebrow mb-3 uppercase tracking-widest font-semibold text-slate">
                            {t.features.eyebrow}
                        </p>
                        <h2 className="heading-section max-w-xl">
                            {t.features.title}
                            <br />
                            <span className="text-primary">{t.features.titleAccent}</span>
                        </h2>
                    </div>
                    <a className="btn-text shrink-0 self-start md:self-auto" href="#how-it-works">
                        {t.features.explore} <ArrowRight size={16} className={isAr ? 'rotate-180' : ''} />
                    </a>
                </div>
            </AnimateIn>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {t.features.items.map((feature, index) => (
                    <AnimateIn key={feature.title} delay={index * 0.1}>
                        <div className="h-full bg-white rounded-[24px] p-8 shadow-sm border border-gray-100 flex flex-col items-start transition-all hover:shadow-md hover:-translate-y-1 duration-300">
                            <div className="w-16 h-16 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center mb-8">
                                <MaskIcon src={featureIcons[index]} size={32} />
                            </div>
                            <h3 className="font-bold text-xl text-ink mb-3">{feature.title}</h3>
                            <p className="text-base md:text-lg text-slate leading-relaxed">{feature.description}</p>
                        </div>
                    </AnimateIn>
                ))}
            </div>
        </section>
    );
}
