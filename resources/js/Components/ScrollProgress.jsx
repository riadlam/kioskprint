import { useEffect, useState } from 'react';

export default function ScrollProgress() {
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const onScroll = () => {
            const doc = document.documentElement;
            const scrollTop = doc.scrollTop || document.body.scrollTop;
            const scrollHeight = doc.scrollHeight - doc.clientHeight;
            setWidth(scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <div className="fixed top-0 left-0 right-0 h-[3px] z-[60] bg-transparent pointer-events-none">
            <div
                className="h-full bg-primary transition-[width] duration-100 ease-out"
                style={{ width: `${width}%` }}
            />
        </div>
    );
}
