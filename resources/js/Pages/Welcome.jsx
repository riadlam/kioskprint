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

export default function Welcome() {
    return (
        <MainLayout>
            <Head>
                <title>Metbati: Algeria&apos;s Smart Printing Kiosk</title>
                <meta
                    name="description"
                    content="Walk up, scan, print, collect. Metbati brings 24/7 self-service printing to campuses, airports, and public spaces across Algeria."
                />
            </Head>
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
