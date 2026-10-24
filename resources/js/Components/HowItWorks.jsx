import AnimateIn from './AnimateIn';
import { ArrowRight, ChevronRight, MaskIcon } from './Icons';

const steps = [
    {
        num: '01',
        title: 'Upload Document',
        text: 'Upload your file in seconds from any device.',
        icon: '/assets/metbati/icons/04-mobile-upload.png',
    },
    {
        num: '02',
        title: 'Choose Kiosk',
        text: 'Select the nearest Metbati kiosk.',
        icon: '/assets/metbati/icons/27-location.png',
    },
    {
        num: '03',
        title: 'Make Payment',
        text: 'Pay securely using cards or digital wallets.',
        icon: '/assets/metbati/icons/05-payment.png',
    },
    {
        num: '04',
        title: 'Collect Print',
        text: 'Collect your high-quality print instantly.',
        icon: '/assets/metbati/icons/07-document.png',
    },
];

export default function HowItWorks() {
    return (
        <section id="how-it-works" className="section-padding section-y bg-[#F8F9FA] border-t border-gray-200">
            <AnimateIn>
                <div className="text-center mb-16">
                    <p className="eyebrow mb-3">PROCESS / HOW IT WORKS</p>
                    <h2 className="heading-section">
                        Print in <span className="text-primary">4 simple steps</span>
                    </h2>
                </div>
            </AnimateIn>
            <div className="relative grid grid-cols-1 md:grid-cols-4 gap-8">
                {steps.map((step, index) => (
                    <AnimateIn key={step.num} delay={index * 0.1} className="relative">
                        <div className="h-full bg-white rounded-3xl p-8 flex flex-col items-center text-center shadow-sm border border-gray-100">
                            <div className="w-24 h-24 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center mb-8 mx-auto">
                                <MaskIcon src={step.icon} size={48} />
                            </div>
                            <div className="text-primary font-bold text-xl mb-3">{step.num}</div>
                            <h3 className="font-bold text-lg text-ink mb-3">{step.title}</h3>
                            <p className="text-sm text-slate leading-relaxed">{step.text}</p>
                        </div>
                        {index < steps.length - 1 && (
                            <div
                                className="hidden md:flex absolute top-1/2 -right-4 translate-x-1/2 -translate-y-1/2 w-8 h-8 rounded-full border border-primary bg-white items-center justify-center text-primary z-10 shadow-sm"
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
                        Find a Kiosk Near You <ArrowRight />
                    </a>
                </div>
            </AnimateIn>
        </section>
    );
}
