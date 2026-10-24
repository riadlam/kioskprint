import Navbar from '../Components/Navbar';
import Footer from '../Components/Footer';
import ScrollProgress from '../Components/ScrollProgress';
import SplashScreen from '../Components/SplashScreen';

export default function MainLayout({ children }) {
    return (
        <>
            <SplashScreen />
            <ScrollProgress />
            <Navbar />
            <main>{children}</main>
            <Footer />
        </>
    );
}
