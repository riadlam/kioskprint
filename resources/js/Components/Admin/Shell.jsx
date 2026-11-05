import { Head, Link, router, usePage } from '@inertiajs/react';

const ADMIN_LINKS = [
    { id: 'overview', href: '/admin', label: 'Overview', labelAr: 'نظرة عامة', roles: ['admin'] },
    { id: 'status', href: '/admin/status', label: 'Status', labelAr: 'الحالة', roles: ['admin'] },
    { id: 'coins', href: '/admin/coins', label: 'Coin probe', labelAr: 'اختبار العملات', roles: ['admin'] },
    { id: 'jobs', href: '/admin#jobs', label: 'Print jobs', labelAr: 'المهام', roles: ['admin'] },
    { id: 'screen', href: '/admin/screen', label: 'Screen', labelAr: 'الشاشة', roles: ['admin'] },
    { id: 'templates', href: '/admin/templates', label: 'Forms', labelAr: 'النماذج', roles: ['admin'] },
    { id: 'professors', href: '/admin/professors', label: 'Professors', labelAr: 'الأساتذة', roles: ['admin'] },
    { id: 'print-codes', href: '/admin/print-codes', label: 'Print codes', labelAr: 'مستنداتي', roles: ['admin', 'professor'] },
];

export default function AdminShell({ title, subtitle, active, email, role, children }) {
    const page = usePage();
    const authUser = page.props.auth?.user;
    const resolvedRole = role || authUser?.role || 'admin';
    const resolvedEmail = email || authUser?.email || '';
    const isProfessor = resolvedRole === 'professor';
    const links = ADMIN_LINKS.filter((link) => link.roles.includes(resolvedRole));
    const roleLabel = isProfessor ? 'أستاذ' : 'Operator';

    return (
        <div className={`min-h-screen bg-[#e8ecea] text-ink ${isProfessor ? 'font-[family-name:var(--font-arabic)]' : ''}`} dir={isProfessor ? 'rtl' : undefined}>
            <Head title={title} />
            <div className="flex min-h-screen">
                <aside className="hidden w-[248px] shrink-0 flex-col bg-[#0f1412] text-white md:flex">
                    <div className="px-6 pb-6 pt-7">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-300">مطبعتـي</p>
                        <p className="mt-1 text-[15px] font-semibold tracking-tight">
                            {isProfessor ? 'لوحة الأستاذ' : 'Operations'}
                        </p>
                    </div>
                    <nav className="flex flex-1 flex-col gap-1 px-3">
                        {links.map((link) => (
                            <NavLink
                                key={link.id}
                                href={link.href}
                                label={isProfessor ? link.labelAr : link.label}
                                active={active === link.id}
                            />
                        ))}
                    </nav>
                    <div className="border-t border-white/10 p-4">
                        <p className="truncate text-[13px] font-medium">{resolvedEmail}</p>
                        <p className="mt-0.5 text-[11px] text-white/50">{roleLabel}</p>
                        <button
                            type="button"
                            onClick={() => router.post('/admin/logout')}
                            className="mt-3 text-[12px] font-semibold text-emerald-300"
                        >
                            {isProfessor ? 'تسجيل الخروج' : 'Sign out'}
                        </button>
                    </div>
                </aside>

                <div className="min-w-0 flex-1">
                    <header className="border-b border-black/5 bg-white/90 px-5 py-4 backdrop-blur md:px-8">
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <h1 className="text-[20px] font-bold tracking-tight text-ink md:text-[22px]">{title}</h1>
                                {subtitle && <p className="mt-1 max-w-2xl text-[13px] leading-relaxed text-slate md:text-[14px]">{subtitle}</p>}
                            </div>
                            <button
                                type="button"
                                onClick={() => router.post('/admin/logout')}
                                className="shrink-0 text-[13px] font-semibold text-primary md:hidden"
                            >
                                {isProfessor ? 'خروج' : 'Sign out'}
                            </button>
                        </div>
                    </header>
                    <nav className="flex gap-2 overflow-x-auto border-b border-black/5 bg-white px-5 py-2.5 md:hidden">
                        {links.map((link) => (
                            <NavLink
                                key={link.id}
                                href={link.href}
                                label={isProfessor ? link.labelAr : link.label}
                                active={active === link.id}
                                compact
                            />
                        ))}
                    </nav>
                    <main className="px-4 py-5 sm:px-6 md:px-8 md:py-8">{children}</main>
                </div>
            </div>
        </div>
    );
}

function NavLink({ href, label, active, compact = false }) {
    return (
        <Link
            href={href}
            className={
                compact
                    ? `shrink-0 rounded-full px-3.5 py-1.5 text-[12px] font-semibold ${active ? 'bg-[#0f1412] text-white' : 'bg-[#eef1f0] text-slate'}`
                    : `rounded-xl px-3 py-2.5 text-[13px] font-medium transition ${active ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/5 hover:text-white'}`
            }
        >
            {label}
        </Link>
    );
}
