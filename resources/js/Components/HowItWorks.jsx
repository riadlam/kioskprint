import AnimateIn from './AnimateIn';
import { ArrowRight, ChevronRight, MaskIcon } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const stepIcons = [
    '/assets/metbati/icons/04-mobile-upload.png',
    '/assets/metbati/icons/27-location.png',
    '/assets/metbati/icons/05-payment.png',
    '/assets/metbati/icons/07-document.png',
];

export default function HowItWorks() {
    const { t, isAr } = useLandingLocale();

    return (
        <section id="how-it-works" className="section-padding section-y bg-[#F8F9FA] border-t border-gray-200">
            <AnimateIn>
                <div className="text-center mb-16">
                    <p className="eyebrow mb-3">{t.how.eyebrow}</p>
                    <h2 className="heading-section">
                        {t.how.title} <span className="text-primary">{t.how.titleAccent}</span>
                    </h2>
                </div>
            </AnimateIn>
            <div className="relative grid grid-cols-1 md:grid-cols-4 gap-8">
                {t.how.steps.map((step, index) => (
                    <AnimateIn key={step.num} delay={index * 0.1} className="relative">
                        <div className="h-full bg-white rounded-3xl p-8 flex flex-col items-center text-center shadow-sm border border-gray-100">
                            <div className="w-24 h-24 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center mb-8 mx-auto">
                                <MaskIcon src={stepIcons[index]} size={48} />
                            </div>
                            <div className="text-primary font-bold text-2xl mb-3">{step.num}</div>
                            <h3 className="font-bold text-xl text-ink mb-3">{step.title}</h3>
                            <p className="text-base md:text-lg text-slate leading-relaxed">{step.text}</p>
                        </div>
                        {index < t.how.steps.length - 1 && (
                            <div
                                className={`hidden md:flex absolute top-1/2 -translate-y-1/2 w-8 h-8 rounded-full border border-primary bg-white items-center justify-center text-primary z-10 shadow-sm ${
                                    isAr
                                        ? '-start-4 -translate-x-1/2 rotate-180'
                                        : '-end-4 translate-x-1/2'
                                }`}
                            >
                                <ChevronRight />
                            </div>
                        )}
                    </AnimateIn>
                ))}
            </div>
            <AnimateIn delay={0.2}>
                <div className="flex justify-center mt-14">
                    <a className="btn-primary" href="#contact">
                        {t.how.cta} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                    </a>
                </div>
            </AnimateIn>
        </section>
    );
}
