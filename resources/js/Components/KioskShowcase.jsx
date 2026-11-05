import AnimateIn from './AnimateIn';
import { ArrowRight, Check } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

export default function KioskShowcase() {
    const { t, isAr } = useLandingLocale();

    return (
        <section className="section-padding section-y bg-white border-t border-gray-200">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <AnimateIn direction="left">
                    <p className="eyebrow mb-3">{t.showcase.eyebrow}</p>
                    <h2 className="heading-section mb-5">{t.showcase.title}</h2>
                    <p className="text-body mb-8 max-w-lg">{t.showcase.body}</p>
                    <ul className="space-y-4 mb-9">
                        {t.showcase.bullets.map((item) => (
                            <li key={item} className="flex items-center gap-3">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <Check />
                                </span>
                                <span className="text-body text-ink">{item}</span>
                            </li>
                        ))}
                    </ul>
                    <a className="btn-secondary" href="#how-it-works">
                        {t.showcase.cta} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                    </a>
                </AnimateIn>
                <AnimateIn direction="right" className="relative flex justify-center w-full">
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 -z-10 m-auto h-[70%] w-[70%] rounded-full bg-primary/5 blur-[90px]"
                    />
                    <img
                        alt={t.showcase.alt}
                        src="/images/kiosk-machine.png"
                        width={700}
                        height={620}
                        className="w-full max-w-[280px] h-auto drop-shadow-2xl recolor-green"
                        onError={(e) => {
                            e.currentTarget.src = '/images/kiosk-fallback.svg';
                        }}
                    />
                </AnimateIn>
            </div>
        </section>
    );
}
