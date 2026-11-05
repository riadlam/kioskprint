import MainLayout from '../Layouts/MainLayout';
import Hero from '../Components/Hero';
import LocationBadges from '../Components/LocationBadges';
import Features from '../Components/Features';
import HowItWorks from '../Components/HowItWorks';
import KioskShowcase from '../Components/KioskShowcase';
import Stats from '../Components/Stats';
import Vision from '../Components/Vision';
import Newsletter from '../Components/Newsletter';
import { Head } from '@inertiajs/react';
import { useLandingLocale } from '../lib/LandingLocale';

function WelcomeHead() {
    const { t } = useLandingLocale();
    return (
        <Head>
            <title>{t.metaTitle}</title>
            <meta name="description" content={t.metaDescription} />
        </Head>
    );
}

export default function Welcome() {
    return (
        <MainLayout>
            <WelcomeHead />
            <Hero />
            <LocationBadges />
            <Features />
            <HowItWorks />
            <KioskShowcase />
            <Stats />
            <Vision />
            <Newsletter />
        </MainLayout>
    );
}
