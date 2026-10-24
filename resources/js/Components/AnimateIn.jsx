import { motion, useReducedMotion } from 'framer-motion';

export default function AnimateIn({
    children,
    className = '',
    delay = 0,
    direction = 'up',
    as = 'div',
}) {
    const reduceMotion = useReducedMotion();
    const Component = motion[as] ?? motion.div;

    const initial = { opacity: 0 };
    const animate = { opacity: 1 };

    if (!reduceMotion) {
        if (direction === 'left') {
            initial.x = -40;
            animate.x = 0;
        } else if (direction === 'right') {
            initial.x = 40;
            animate.x = 0;
        } else {
            initial.y = 40;
            animate.y = 0;
        }
    }

    return (
        <Component
            className={className}
            initial={initial}
            whileInView={animate}
            viewport={{ once: true, margin: '-80px' }}
            transition={{ duration: 0.6, delay, ease: [0.22, 1, 0.36, 1] }}
        >
            {children}
        </Component>
    );
}
