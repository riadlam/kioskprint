import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { LANDING_LOCALE_KEY, landingCopy } from './landingCopy';

const LandingLocaleContext = createContext({
    locale: 'ar',
    setLocale: () => {},
    t: landingCopy.ar,
    isAr: true,
});

export function LandingLocaleProvider({ children }) {
    const [locale, setLocaleState] = useState(() => {
        if (typeof window === 'undefined') {
            return 'ar';
        }
        const saved = window.localStorage.getItem(LANDING_LOCALE_KEY);
        return saved === 'en' || saved === 'ar' ? saved : 'ar';
    });

    const setLocale = (next) => {
        const value = next === 'en' ? 'en' : 'ar';
        setLocaleState(value);
        try {
            window.localStorage.setItem(LANDING_LOCALE_KEY, value);
        } catch (_) {}
    };

    useEffect(() => {
        document.documentElement.lang = locale === 'ar' ? 'ar' : 'en';
        document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
        document.body.classList.toggle('font-arabic', locale === 'ar');
        return () => {
            document.body.classList.remove('font-arabic');
        };
    }, [locale]);

    const value = useMemo(
        () => ({
            locale,
            setLocale,
            t: landingCopy[locale] || landingCopy.ar,
            isAr: locale === 'ar',
        }),
        [locale],
    );

    return <LandingLocaleContext.Provider value={value}>{children}</LandingLocaleContext.Provider>;
}

export function useLandingLocale() {
    return useContext(LandingLocaleContext);
}
