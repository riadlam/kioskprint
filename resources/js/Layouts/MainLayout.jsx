import Navbar from '../Components/Navbar';
import Footer from '../Components/Footer';
import ScrollProgress from '../Components/ScrollProgress';
import SplashScreen from '../Components/SplashScreen';
import { LandingLocaleProvider } from '../lib/LandingLocale';

export default function MainLayout({ children }) {
    return (
        <LandingLocaleProvider>
            <SplashScreen />
            <ScrollProgress />
            <Navbar />
            <main>{children}</main>
            <Footer />
        </LandingLocaleProvider>
    );
}
